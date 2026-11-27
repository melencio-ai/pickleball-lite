<?php

namespace App\Http\Controllers;

use App\Models\OpenPlayRegistration;
use App\Models\Venue;
use App\Services\OpenPlay;
use App\Services\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicOpenPlayController extends Controller
{
    public function index(Request $request)
    {
        $venue = \App\Services\VenueContext::venue();
        $today = CarbonImmutable::today($venue->timezone);
        $lastDay = $today->addDays($venue->advance_days);
        $date = $request->query('date');
        if (Validator::make(['date' => $date], ['date' => [
            'required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$lastDay->toDateString(),
        ]])->fails()) {
            $date = null;
        }

        $upcoming = $venue->openPlaySessions()->whereNull('cancelled_at')->where('starts_at', '>', now())
            ->where('starts_at', '<', $lastDay->addDay()->utc())
            ->withCount(['registrations as active_count' => fn ($query) => $query->occupying(), 'courts'])
            ->orderBy('starts_at')->get();
        $dates = $upcoming->groupBy(fn ($session) => $session->starts_at->setTimezone($venue->timezone)->toDateString());
        $sessions = $date ? $dates->get($date, collect()) : collect();
        $selected = $request->query('session');
        $session = is_string($selected) ? $sessions->first(fn ($row) => (string) $row->id === $selected) : null;
        $step = $session ? 3 : ($date ? 2 : 1);
        $media = $venue->media()->get(['kind', 'version'])->keyBy('kind');

        return view('open-play.index', compact('venue', 'today', 'date', 'dates', 'sessions', 'session', 'step', 'media'));
    }

    public function store(Request $request, OpenPlay $openPlay, int $session)
    {
        $venue = \App\Services\VenueContext::venue();
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:'.Schedule::PHONE],
            'quoted_total' => ['required', 'integer', 'min:1'],
        ]);
        $record = $openPlay->register($venue, $session, $data, 'online');

        return \App\Services\VenueContext::redirect('open-play.payment', $record->payment_token);
    }

    public function payment(string $token)
    {
        $venue = \App\Services\VenueContext::venue();
        $registration = $this->registration($venue, $token);
        $status = $registration->paymentStatus();

        return response()->view('open-play.payment', compact('venue', 'registration', 'status'))->withHeaders([
            'Cache-Control' => 'private, no-store',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    public function paymentStatus(string $token)
    {
        $venue = \App\Services\VenueContext::venue();

        return response()->json(['status' => $this->registration($venue, $token)->paymentStatus()])
            ->header('Cache-Control', 'private, no-store');
    }

    private function registration(Venue $venue, string $token): OpenPlayRegistration
    {
        return OpenPlayRegistration::query()
            ->whereIn('open_play_session_id', $venue->openPlaySessions()->select('id'))
            ->with('session')->where('payment_token', $token)->firstOrFail();
    }
}
