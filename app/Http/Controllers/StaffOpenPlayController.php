<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\OpenPlay;
use App\Services\Pricing;
use App\Services\Schedule;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffOpenPlayController extends Controller
{
    public function index(Request $request)
    {
        $venue = \App\Services\VenueContext::venue();
        $filter = $request->query('filter') === 'past' ? 'past' : 'upcoming';
        $sessions = $venue->openPlaySessions()->with('courts')
            ->withCount(['registrations as active_count' => fn ($query) => $query->occupying(),
                'registrations as paid_count' => fn ($query) => $query->whereNull('cancelled_at')->whereNotNull('paid_at')])
            ->when($filter === 'upcoming', fn ($query) => $query->where('ends_at', '>', now())->orderBy('starts_at'),
                fn ($query) => $query->where('ends_at', '<=', now())->orderByDesc('starts_at'))
            ->paginate(25)->withQueryString();
        $courts = $venue->courts()->where('active', true)->orderBy('id')->get();

        return view('staff.open-play.index', compact('venue', 'sessions', 'courts', 'filter'));
    }

    public function store(Request $request, OpenPlay $openPlay)
    {
        $venue = \App\Services\VenueContext::venue();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'regex:'.Schedule::START_TIME],
            'end' => ['required', 'regex:'.Schedule::END_TIME],
            'court_ids' => ['required', 'array', 'min:1'],
            'court_ids.*' => ['integer', 'distinct', Rule::exists('courts', 'id')->where('venue_id', $venue->id)],
            'capacity' => ['required', 'integer', 'between:1,500'],
            'price' => ['required', 'regex:'.Pricing::PESOS, 'numeric', 'min:0.01'],
            'skill_level' => ['required', 'in:all,beginner,intermediate,advanced'],
            'repeat' => ['required', 'in:none,daily,weekly'],
            'occurrences' => ['required_unless:repeat,none', 'nullable', 'integer', 'between:1,30'],
        ]);
        $sessions = $openPlay->create($venue, $data);

        if ($request->expectsJson()) {
            return response()->json(['message' => count($sessions).' session(s) published.']);
        }

        return \App\Services\VenueContext::redirect('staff.open-play.show', $sessions[0]->id)->with('status', count($sessions).' session(s) published.');
    }

    public function show(Request $request, int $session)
    {
        $venue = \App\Services\VenueContext::venue();
        $record = $venue->openPlaySessions()->with('courts')->findOrFail($session);
        $registrations = $record->registrations()->orderBy('id')->get();
        $active = $registrations->filter(fn ($player) => ! $player->cancelled_at && ! $player->paymentExpired());
        $paid = $active->filter(fn ($player) => $player->paid_at !== null);
        $collected = $paid->sum('total');

        return view('staff.open-play.show', compact('venue', 'record', 'registrations', 'active', 'paid', 'collected'));
    }

    public function register(Request $request, OpenPlay $openPlay, int $session)
    {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_phone' => ['required', 'string', 'regex:'.Schedule::PHONE],
        ]);
        $openPlay->register(\App\Services\VenueContext::venue(), $session, $data, 'staff');

        return $this->result($request, 'Player slot held. Share the payment link with the player.');
    }

    public function action(Request $request, OpenPlay $openPlay, int $session, int $registration, string $action)
    {
        abort_unless(in_array($action, ['paid', 'cancel', 'check-in'], true), 404);
        $data = $action === 'paid' ? $request->validate([
            'received_amount' => ['required', 'regex:'.Pricing::PESOS],
            'payment_reference' => ['required', 'string', 'regex:/^[A-Za-z0-9-]{4,64}$/D'],
            'verified_in_messenger' => ['accepted'],
        ]) : [];
        $openPlay->updateRegistration(\App\Services\VenueContext::venue(), $session, $registration, $action, $data, $request->user()->id);

        return $this->result($request, match ($action) {
            'paid' => 'Payment verified.',
            'cancel' => 'Registration cancelled. Reconcile any payment in Messenger.',
            'check-in' => 'Player checked in.',
        });
    }

    public function cancel(Request $request, OpenPlay $openPlay, int $session)
    {
        $openPlay->cancelSession(\App\Services\VenueContext::venue(), $session);

        return $this->result($request, 'Session cancelled and courts released. Reconcile any paid registrations in Messenger.');
    }

    private function result(Request $request, string $message)
    {
        return $request->expectsJson() ? response()->json(['message' => $message]) : back()->with('status', $message);
    }
}
