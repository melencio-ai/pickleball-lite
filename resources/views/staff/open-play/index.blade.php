@extends('layout')
@section('title', 'Open Play')
@section('content')
    <div class="page-heading section-heading">
        <div><h1>Open Play</h1><p class="muted">Schedule sessions and manage player registrations.</p></div>
        <a class="button" href="#create-session">Create Open Play</a>
    </div>
    @if(!$venue->qr_payments_enabled || !$venue->payment_qr_base || !$venue->payment_messenger_url)
        <p class="notice">Set up venue QR payments and Messenger before accepting registrations. @can('manage-venue')<a href="{{ \App\Services\VenueContext::url('settings.customization') }}#payments">Payment settings</a>@endcan</p>
    @endif
    <nav class="actions" aria-label="Session filters">
        <a class="button {{ $filter === 'upcoming' ? '' : 'secondary' }}" href="{{ \App\Services\VenueContext::url('staff.open-play') }}">Upcoming</a>
        <a class="button {{ $filter === 'past' ? '' : 'secondary' }}" href="{{ \App\Services\VenueContext::url('staff.open-play', ['filter' => 'past']) }}">Past</a>
        <a class="button secondary" href="{{ \App\Services\VenueContext::url('open-play') }}" target="_blank" rel="noopener">Public registration</a>
    </nav>
    <section class="panel open-play-admin-list">
        @forelse($sessions as $session)
            <article class="open-play-list-row">
                <div>
                    <a class="session-name" href="{{ \App\Services\VenueContext::url('staff.open-play.show', $session->id) }}">{{ $session->name }}</a>
                    <p class="muted">{{ $session->starts_at->setTimezone($venue->timezone)->format('D, M j · g:i A') }} – {{ $session->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</p>
                    <p class="muted">{{ $session->courts->pluck('name')->join(', ') }} · {{ $session->levelLabel() }}</p>
                </div>
                <div><strong>{{ $session->paid_count }}/{{ $session->capacity }}</strong> paid<br><small class="muted">{{ $session->spotsLeft() }} spots left</small></div>
                <div><strong>₱{{ number_format($session->price / 100, 2) }}</strong><br><small class="muted">per player</small></div>
                <span class="status-chip {{ $session->cancelled_at ? 'unpaid' : 'paid' }}">{{ $session->cancelled_at ? 'Cancelled' : ($session->ends_at->lte(now()) ? 'Ended' : 'Published') }}</span>
                <a class="button secondary small" href="{{ \App\Services\VenueContext::url('staff.open-play.show', $session->id) }}">Manage</a>
            </article>
        @empty
            <p class="muted">No {{ $filter }} Open Play sessions.</p>
        @endforelse
        <div class="actions">
            @if($sessions->previousPageUrl())<a class="button secondary" href="{{ $sessions->previousPageUrl() }}">Previous</a>@endif
            @if($sessions->nextPageUrl())<a class="button secondary" href="{{ $sessions->nextPageUrl() }}">Next</a>@endif
        </div>
    </section>
    <section class="panel open-play-create" id="create-session">
        <h2>Create Open Play</h2>
        <p class="muted">Publish one session or a series. Assigned courts are reserved for the entire session.</p>
        <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.store') }}">
            @csrf
            <label>Session name<input name="name" value="{{ old('name', 'Open Play') }}" maxlength="100" required></label>
            <div class="open-play-form-grid">
                <label>Date<input type="date" name="date" min="{{ now($venue->timezone)->toDateString() }}" value="{{ old('date', is_string(request()->query('date')) ? request()->query('date') : now($venue->timezone)->toDateString()) }}" required></label>
                <label>Start time<input type="time" name="start" step="1800" value="{{ old('start', '18:00') }}" required></label>
                <label>End time<input name="end" value="{{ old('end', '22:00') }}" pattern="([01][0-9]|2[0-3]):(00|30)|24:00" placeholder="22:00" required></label>
                <label>Maximum players<input type="number" name="capacity" min="1" max="500" value="{{ old('capacity', 24) }}" required></label>
                <label>Price per player (PHP)<input name="price" type="number" min="0.01" max="999999" step="0.01" value="{{ old('price', 200) }}" required></label>
                <label>Skill level<select name="skill_level">@foreach(['all' => 'All skill levels', 'beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'] as $value => $label)<option value="{{ $value }}" @selected(old('skill_level', 'all') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label>Repeat<select name="repeat" data-repeat>@foreach(['none' => 'One-time session', 'daily' => 'Every day', 'weekly' => 'Every week'] as $value => $label)<option value="{{ $value }}" @selected(old('repeat', 'none') === $value)>{{ $label }}</option>@endforeach</select></label>
                <label data-occurrences>Number of sessions<input type="number" name="occurrences" min="1" max="30" value="{{ old('occurrences', 1) }}"></label>
            </div>
            <fieldset class="hours-row">
                <legend>Assign courts</legend>
                @forelse($courts as $court)
                    <label class="checkbox"><input type="checkbox" name="court_ids[]" value="{{ $court->id }}" @checked(in_array((string) $court->id, array_map('strval', (array) old('court_ids', [])), true))> {{ $court->name }}</label>
                @empty
                    <p class="muted">No active courts. Add one in Settings.</p>
                @endforelse
            </fieldset>
            <button class="button" @disabled($courts->isEmpty())>Publish Open Play</button>
        </form>
    </section>
    <script src="{{ asset('open-play-admin.js') }}" defer></script>
@endsection
