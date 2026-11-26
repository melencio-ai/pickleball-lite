<?php

namespace App\Http\Controllers;

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

        $today = CarbonImmutable::today($venue->timezone);
        $lastDay = $today->addDays(30);
        $date = $request->query('date');
        if (Validator::make(['date' => $date], ['date' => [
            'required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$lastDay->toDateString(),
        ]])->fails()) {
            $date = $today->toDateString();
        }
        $duration = $request->query('duration') === '120' ? 120 : 60;
        $courts = $schedule->availability($venue, $date, $duration);

        $selectedCourt = $request->query('court');
        $selectedStart = $request->query('start');
        $selection = collect($courts)->first(fn ($offer) => (string) $offer['court']->id === $selectedCourt
            && is_string($selectedStart) && isset($offer['slots'][$selectedStart]));

        return view('book.index', compact('venue', 'date', 'duration', 'courts', 'today', 'lastDay', 'selection', 'selectedStart'));
    }

    public function store(Request $request, Schedule $schedule)
    {
        $venue = Venue::query()->orderBy('id')->firstOrFail();
        $today = CarbonImmutable::today($venue->timezone);
        $data = $request->validate([
            'court_id' => ['required', 'integer', Rule::exists('courts', 'id')->where('venue_id', $venue->id)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addDays(30)->toDateString()],
            'start' => ['required', 'regex:'.Schedule::START_TIME],
            'duration' => ['required', 'in:60,120'],
            'quoted_total' => ['required', 'integer', 'min:0'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:'.Schedule::PHONE],
        ]);
        $booking = $schedule->reserve($venue, $data, 'online');
        $court = $venue->courts()->findOrFail($booking->court_id);
        $request->session()->put('booking_confirmation', [
            'reference' => strtoupper($booking->reference),
            'court' => $court->name,
            'date' => $booking->starts_at->setTimezone($venue->timezone)->format('l, F j, Y'),
            'time' => $booking->starts_at->setTimezone($venue->timezone)->format('g:i A').' – '.$booking->ends_at->setTimezone($venue->timezone)->format('g:i A'),
            'total' => $booking->total,
        ]);

        return redirect()->route('book.confirmed');
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
