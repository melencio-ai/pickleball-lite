<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\Pricing;
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
    public function index(Request $request, Schedule $schedule)
    {
        $venue = $this->venue($request);
        $requested = $request->query('date');
        $valid = Validator::make(['date' => $requested], ['date' => 'required|date_format:Y-m-d'])->passes();
        $day = $valid ? CarbonImmutable::parse($requested, $venue->timezone) : CarbonImmutable::today($venue->timezone);
        $date = $day->toDateString();
        $start = $day->utc();
        $end = $day->addDay()->utc();
        $hours = $schedule->hours($venue, $date);
        $openSlots = collect($schedule->availability($venue, $date, $venue->defaultDuration(), public: false))->keyBy('court.id');
        $courts = $venue->courts()->orderBy('id')->get()->map(function ($court) use ($start, $end, $schedule, $openSlots) {
            $court->setRelation('bookings', $schedule->overlaps($court->bookings(), $start, $end)->orderBy('starts_at')->get());
            $court->setRelation('blocks', $schedule->overlaps($court->blocks(), $start, $end)->orderBy('starts_at')->get());
            $court->setAttribute('slots', $openSlots->get($court->id)['slots'] ?? []);

            return $court;
        })->filter(fn ($court) => $court->active || $court->bookings->isNotEmpty() || $court->blocks->isNotEmpty());

        return view('staff.calendar', compact('venue', 'day', 'date', 'courts', 'hours'));
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
        return $request->user()->venue()->firstOrFail();
    }

    private function calendar(string $date, string $status)
    {
        return redirect()->route('staff.calendar', ['date' => $date])->with('status', $status);
    }
}
