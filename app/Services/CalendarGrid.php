<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CalendarGrid
{
    public function build(Venue $venue, CarbonImmutable $day, $hours, Collection $courts, Schedule $schedule): array
    {
        $dayStart = $day->utc();
        $dayEnd = $day->addDay()->utc();
        $open = $hours && !$hours->closed ? $schedule->time($venue, $day->toDateString(), $hours->opens_at) : null;
        $close = $open ? $schedule->time($venue, $day->toDateString(), $hours->closes_at) : null;
        $history = [];
        $visible = [];
        $sessionIds = [];
        foreach ($courts->values() as $column => $court) {
            foreach ($court->bookings->concat($court->blocks) as $entry) {
                if ($entry instanceof Booking && ($entry->cancelled_at || $entry->paymentExpired())) {
                    $history[] = ['court' => $court, 'entry' => $entry];
                    continue;
                }
                $visible[$column][] = $entry;
                if (!($entry instanceof Booking) && $entry->open_play_session_id) {
                    $sessionIds[] = $entry->open_play_session_id;
                }
            }
        }
        $sessions = $venue->openPlaySessions()->whereIn('id', array_unique($sessionIds))
            ->withCount(['registrations as active_count' => fn ($q) => $q->occupying(),
                'registrations as paid_count' => fn ($q) => $q->whereNull('cancelled_at')->whereNotNull('paid_at')])
            ->get()->keyBy('id');
        // Keep existing reservations visible even if opening hours were subsequently shortened.
        $first = $open;
        $last = $close;
        foreach ($visible as $entries) {
            foreach ($entries as $entry) {
                $s = max($dayStart, $entry->starts_at);
                $e = min($dayEnd, $entry->ends_at);
                $first = $first ? min($first, $s) : $s;
                $last = $last ? max($last, $e) : $e;
            }
        }
        if (!$first || !$last || $last->lte($first)) {
            return ['slots' => [], 'events' => [], 'free' => [], 'history' => $history, 'currentLine' => null];
        }
        $slots = [];
        $free = [];
        for ($cursor = $first; $cursor->lt($last); $cursor = $cursor->addMinutes(30)) {
            $row = count($slots) + 2;
            $local = $cursor->setTimezone($venue->timezone);
            $slots[] = ['row' => $row, 'label' => $local->format('g:i A')];
            foreach ($courts->values() as $column => $court) {
                if (!$court->active || !$open || $cursor->lt($open) || $cursor->addMinutes(30)->gt($close)
                    || $cursor->lte(now()) || $local->toDateString() !== $day->toDateString()
                    || !$cursor->equalTo($schedule->time($venue, $day->toDateString(), $local->format('H:i')))) {
                    continue;
                }
                $occupied = collect($visible[$column] ?? [])->contains(fn ($entry) =>
                    $entry->starts_at->lt($cursor->addMinutes(30)) && $entry->ends_at->gt($cursor));
                if (!$occupied) {
                    $free[] = ['row' => $row, 'column' => $column + 2, 'court' => $court, 'time' => $local->format('H:i')];
                }
            }
        }
        $events = [];
        foreach ($visible as $column => $entries) {
            foreach ($entries as $entry) {
                $row = 2 + max(0, (int) floor($first->diffInMinutes(max($first, $entry->starts_at), false) / 30));
                $endRow = 2 + (int) ceil($first->diffInMinutes(min($last, $entry->ends_at), false) / 30);
                if ($endRow <= $row) continue;
                $session = $entry instanceof Booking ? null : $sessions->get($entry->open_play_session_id);
                $kind = $entry instanceof Booking ? 'booking' : ($session ? 'session' : 'blocked');
                $id = $session ? $session->id : $entry->id;
                $status = $kind === 'booking' ? ($entry->paid_at ? 'paid' : ($entry->payment_token ? 'pending' : 'unpaid')) : ($session ? 'open-play' : 'blocked');
                $events[] = ['row' => $row, 'rows' => $endRow - $row, 'column' => $column + 2, 'columns' => 1,
                    'kind' => $kind, 'id' => $id, 'status' => $status,
                    'title' => $entry instanceof Booking ? $entry->customer_name : ($session ? $session->name : $entry->reason),
                    'time' => $entry->starts_at->setTimezone($venue->timezone)->format('g:i A').' – '.$entry->ends_at->setTimezone($venue->timezone)->format('g:i A'),
                    'summary' => $session ? $session->paid_count.'/'.$session->capacity.' paid · '.$session->spotsLeft().' left' : ($status === 'pending' ? 'Pending QR' : ucfirst($status)),
                    'expires' => $status === 'pending' ? $entry->payment_expires_at?->getTimestamp() : null];
            }
        }
        // Join only adjacent court columns belonging to the same session; never span an unrelated court.
        $merged = [];
        foreach ($events as $event) {
            $key = $event['kind'].':'.$event['id'];
            $previous = $merged[$key] ?? null;
            if ($event['kind'] === 'session' && $previous && $previous['column'] + $previous['columns'] === $event['column']
                && $previous['row'] === $event['row'] && $previous['rows'] === $event['rows']) {
                $merged[$key]['columns']++;
            } else {
                if ($previous) $merged[$key.':'.$previous['column']] = $previous;
                $merged[$key] = $event;
            }
        }
        $currentLine = now()->gte($first) && now()->lt($last)
            ? $first->diffInMinutes(now(), false) / 30 : null;

        return ['slots' => $slots, 'events' => array_values($merged), 'free' => $free, 'history' => $history, 'currentLine' => $currentLine];
    }
}
