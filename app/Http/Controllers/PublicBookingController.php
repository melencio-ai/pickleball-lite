<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Venue;
use App\Services\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class PublicBookingController extends Controller
{
    public function index(Request $request, Schedule $schedule)
    {
        $venue = Venue::query()->orderBy('id')->first();
        if (! $venue) {
            return response()->view('book.setup', [], 503);
        }

        if (!$request->hasAny(['step', 'date', 'duration', 'court', 'start']) && !$request->session()->has('errors')) {
            $media = $venue->media()->get(['kind', 'version'])->keyBy('kind');

            return view('book.choose', compact('venue', 'media'));
        }

        $today = CarbonImmutable::today($venue->timezone);
        $lastDay = $today->addDays($venue->advance_days);
        $date = $request->query('date');
        if (Validator::make(['date' => $date], ['date' => [
            'required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$lastDay->toDateString(),
        ]])->fails()) {
            $date = $today->toDateString();
        }
        $requested = $request->query('duration');
        $duration = is_string($requested) && in_array($requested, array_map('strval', $venue->durations()), true)
            ? (int) $requested : $venue->defaultDuration();
        $courts = $schedule->availability($venue, $date, $duration);

        $selectedCourt = $request->query('court');
        $selectedStart = $request->query('start');
        $selection = collect($courts)->first(fn ($offer) => (string) $offer['court']->id === $selectedCourt
            && is_string($selectedStart) && isset($offer['slots'][$selectedStart]));

        $media = $venue->media()->get(['kind', 'version'])->keyBy('kind');

        return view('book.index', compact('venue', 'date', 'duration', 'courts', 'today', 'lastDay', 'selection', 'selectedStart', 'media'));
    }

    public function store(Request $request, Schedule $schedule)
    {
        $venue = Venue::query()->orderBy('id')->firstOrFail();
        $today = CarbonImmutable::today($venue->timezone);
        $data = $request->validate([
            'court_id' => ['required', 'integer', Rule::exists('courts', 'id')->where('venue_id', $venue->id)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addDays($venue->advance_days)->toDateString()],
            'start' => ['required', 'regex:'.Schedule::START_TIME],
            'duration' => ['required', 'integer', Rule::in($venue->durations())],
            'quoted_total' => ['required', 'integer', 'min:0'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:'.Schedule::PHONE],
        ]);
        $booking = $schedule->reserve($venue, $data, 'online');
        if ($booking->payment_token) {
            return redirect()->route('book.payment', ['token' => $booking->payment_token]);
        }

        $court = $venue->courts()->findOrFail($booking->court_id);
        $request->session()->put('booking_confirmation', [
            'reference' => strtoupper($booking->reference),
            'court' => $court->name,
            'date' => $booking->starts_at->setTimezone($venue->timezone)->format('l, F j, Y'),
            'time' => $booking->starts_at->setTimezone($venue->timezone)->format('g:i A').' – '.$booking->ends_at->setTimezone($venue->timezone)->format('g:i A'),
            'total' => $booking->total,
            'cancellation_policy' => $booking->cancellation_policy,
        ]);

        return redirect()->route('book.confirmed');
    }

    public function payment(string $token)
    {
        $venue = Venue::query()->orderBy('id')->firstOrFail();
        $booking = $this->paymentBooking($venue, $token)->load('court');
        $status = $booking->paymentStatus();

        return response()->view('book.payment', compact('venue', 'booking', 'status'))->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function paymentStatus(string $token)
    {
        $venue = Venue::query()->orderBy('id')->firstOrFail();
        $booking = $this->paymentBooking($venue, $token);

        return response()->json(['status' => $booking->paymentStatus()])->header('Cache-Control', 'private, no-store');
    }

    private function paymentBooking(Venue $venue, string $token): Booking
    {
        return Booking::query()->whereIn('court_id', $venue->courts()->select('id'))
            ->where('payment_token', $token)->firstOrFail();
    }

    public function image(string $kind, string $version)
    {
        $venue = Venue::query()->orderBy('id')->firstOrFail();
        $image = $venue->media()->where('kind', $kind)->where('version', $version)->firstOrFail();

        return response(base64_decode($image->content, true), 200, [
            'Content-Type' => $image->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }

    public function confirmation(Request $request)
    {
        $confirmation = $request->session()->get('booking_confirmation');
        if (! $confirmation) {
            return redirect()->route('book');
        }

        return response()->view('book.confirmation', compact('confirmation'))->header('Cache-Control', 'private, no-store');
    }
}
