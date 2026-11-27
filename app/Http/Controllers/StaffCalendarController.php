<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Pricing;
use App\Services\CalendarGrid;
use App\Models\Booking;
use App\Models\CourtBlock;
use App\Services\PaymentReferences;
use App\Services\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffCalendarController extends Controller
{
    public function index(Request $request, Schedule $schedule, CalendarGrid $calendar)
    {
        $venue = $this->venue($request);
        $requested = $request->query('date');
        $valid = Validator::make(['date' => $requested], ['date' => 'required|date_format:Y-m-d'])->passes();
        $day = $valid ? CarbonImmutable::parse($requested, $venue->timezone) : CarbonImmutable::today($venue->timezone);
        $date = $day->toDateString();
        $start = $day->utc();
        $end = $day->addDay()->utc();
        $hours = $schedule->hours($venue, $date);
        $courts = $venue->courts()->orderBy('id')->get()->map(function ($court) use ($start, $end, $schedule) {
            $court->setRelation('bookings', $schedule->overlaps($court->bookings(), $start, $end)->orderBy('starts_at')->get());
            $court->setRelation('blocks', $schedule->overlaps($court->blocks(), $start, $end)->orderBy('starts_at')->get());

            return $court;
        })->filter(fn ($court) => $court->active || $court->bookings->isNotEmpty() || $court->blocks->isNotEmpty());

        $grid = $calendar->build($venue, $day, $hours, $courts, $schedule);
        $data = compact('venue', 'day', 'date', 'courts', 'hours', 'grid');
        if ($request->query('fragment') === 'grid') {
            return response()->view('staff.calendar.grid', $data)->header('Cache-Control', 'private, no-store');
        }
        $action = $request->query('action');
        $data['initialModal'] = in_array($action, ['new', 'book', 'block', 'open-play'], true)
            ? \App\Services\VenueContext::url('staff.calendar.modal', ['kind' => $action, 'date' => $date,
                'court' => is_scalar($request->query('court')) ? $request->query('court') : null,
                'start' => is_scalar($request->query('start')) ? $request->query('start') : null])
            : null;
        $data['initialTitle'] = $action === 'book' ? 'Add a booking' : null;

        return response()->view('staff.calendar', $data)->header('Cache-Control', 'private, no-store');
    }

    public function modal(Request $request, string $kind, ?int $record = null)
    {
        $venue = $this->venue($request);
        $date = $request->query('date');
        $date = Validator::make(['date' => $date], ['date' => 'required|date_format:Y-m-d'])->passes()
            ? $date : now($venue->timezone)->toDateString();
        $data = compact('venue', 'kind', 'date');
        if (in_array($kind, ['new', 'book', 'block', 'open-play'], true)) {
            $courts = $venue->courts()->where('active', true)->orderBy('id')->get();
            $selectedCourt = $request->query('court');
            $selectedCourt = is_scalar($selectedCourt) ? $courts->first(fn ($court) => (string) $court->id === (string) $selectedCourt) : null;
            $selectedStart = $request->query('start');
            $selectedStart = is_string($selectedStart) && preg_match(Schedule::START_TIME, $selectedStart) ? $selectedStart : '';
            $data += compact('courts', 'selectedCourt', 'selectedStart');
        } elseif ($kind === 'booking') {
            $entry = Booking::whereIn('court_id', $venue->courts()->select('id'))->with('court')->findOrFail($record);
            $data += compact('entry');
        } elseif ($kind === 'blocked') {
            $entry = CourtBlock::whereIn('court_id', $venue->courts()->select('id'))->whereNull('open_play_session_id')->findOrFail($record);
            $court = $venue->courts()->findOrFail($entry->court_id);
            $data += compact('entry', 'court');
        } elseif ($kind === 'session') {
            $session = $venue->openPlaySessions()->with('courts')->findOrFail($record);
            $players = $session->registrations()->orderBy('id')->get();
            $active = $players->filter(fn ($player) => !$player->cancelled_at && !$player->paymentExpired());
            $paid = $active->filter(fn ($player) => $player->paid_at !== null);
            $data += compact('session', 'players', 'active', 'paid');
        } else {
            abort(404);
        }

        return response()->view($request->header('X-Calendar-Fragment') === '1'
            ? 'staff.calendar.modal' : 'staff.calendar.modal-page', $data)->header('Cache-Control', 'private, no-store');
    }

    public function book(Request $request, Schedule $schedule)
    {
        $venue = $this->venue($request);
        $data = $request->validate($this->slotRules($venue) + [
            'duration' => ['required', 'integer', Rule::in($venue->durations())],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:'.Schedule::PHONE],
        ]);
        $booking = $schedule->reserve($venue, $data, 'staff');

        return $this->calendar($data['date'], $booking->payment_token ? 'Court held pending QR payment.' : 'Booking confirmed.');
    }

    public function block(Request $request, Schedule $schedule)
    {
        $venue = $this->venue($request);
        $data = $request->validate($this->slotRules($venue) + [
            'end' => ['required', 'regex:'.Schedule::END_TIME],
            'reason' => ['required', 'string', 'max:120'],
        ]);
        $schedule->block($venue, $data);

        return $this->calendar($data['date'], 'Court time blocked.');
    }

    public function paid(Request $request, int $booking)
    {
        try {
            return $this->updateBooking($request, $booking, true);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['payment_reference' => 'That transaction reference is already recorded on a booking.']);
        }
    }

    public function cancel(Request $request, int $booking)
    {
        return $this->updateBooking($request, $booking, false);
    }

    private function updateBooking(Request $request, int $booking, bool $pay)
    {
        $venue = $this->venue($request);
        $date = DB::transaction(function () use ($request, $venue, $booking, $pay) {
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            $court = $venue->courts()->whereHas('bookings', fn ($query) => $query->whereKey($booking))
                ->lockForUpdate()->firstOrFail();
            $record = $court->bookings()->lockForUpdate()->findOrFail($booking);
            if ($pay && $record->cancelled_at) {
                throw ValidationException::withMessages(['booking' => 'This reservation was cancelled.']);
            }

            $field = $pay ? 'paid_at' : 'cancelled_at';
            if (! $record->$field) {
                if ($pay && $record->payment_token) {
                    if ($record->paymentExpired()) {
                        throw ValidationException::withMessages(['booking' => 'This hold expired. Reconcile the payment with the player in Messenger.']);
                    }

                    $proof = $request->validate([
                        'received_amount' => ['required', 'regex:'.Pricing::PESOS],
                        'payment_reference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{4,64}$/D'],
                        'verified_in_messenger' => ['accepted'],
                    ]);
                    if (Pricing::centavos($proof['received_amount']) !== $record->total) {
                        throw ValidationException::withMessages(['received_amount' => 'The amount received must match the booking total.']);
                    }

                    $record->payment_reference = PaymentReferences::claim($proof['payment_reference']);
                    $record->payment_verified_by = $request->user()->id;
                }

                $record->$field = now();
                $record->save();
                \App\Services\VenueNotifications::emit($venue,$pay?'payment':'booking',($pay?'booking-paid-':'cancelled-').$record->id,
                    $pay?'Court payment verified':'Court booking cancelled',$court->name.' · '.$record->starts_at->setTimezone($venue->timezone)->format('M j, g:i A'),'calendar');
            }

            return $record->starts_at->setTimezone($venue->timezone)->toDateString();
        }, 3);

        return $this->calendar($date, $pay ? 'Payment marked as received.' : 'Booking cancelled.');
    }

    public function unblock(Request $request, int $block)
    {
        $venue = $this->venue($request);
        $date = DB::transaction(function () use ($venue, $block) {
            $venue = Venue::query()->sharedLock()->findOrFail($venue->id);
            $court = $venue->courts()->whereHas('blocks', fn ($query) => $query->whereKey($block))
                ->lockForUpdate()->firstOrFail();
            $record = $court->blocks()->lockForUpdate()->findOrFail($block);
            $date = $record->starts_at->setTimezone($venue->timezone)->toDateString();
            if ($record->open_play_session_id) {
                throw ValidationException::withMessages(['block' => 'Cancel the Open Play session to release its courts.']);
            }
            $record->delete();

            return $date;
        }, 3);

        return $this->calendar($date, 'Court block removed.');
    }

    private function slotRules(Venue $venue): array
    {
        return [
            'court_id' => ['required', 'integer', Rule::exists('courts', 'id')->where('venue_id', $venue->id)],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'regex:'.Schedule::START_TIME],
        ];
    }

    private function venue(Request $request): Venue
    {
        return \App\Services\VenueContext::venue();
    }

    private function calendar(string $date, string $status)
    {
        if (request()->expectsJson()) {
            return response()->json(['message' => $status, 'date' => $date]);
        }

        return \App\Services\VenueContext::redirect('staff.calendar', ['date' => $date])->with('status', $status);
    }
}
