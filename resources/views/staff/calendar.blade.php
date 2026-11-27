@extends('layout')
@section('title', 'Court calendar')
@section('page-class', 'calendar-page')
@section('content')
    <header class="section-heading page-heading calendar-heading">
        <div><p class="venue-name">{{ $venue->name }}</p><h1>Court calendar</h1></div>
        <a class="button" href="{{ \App\Services\VenueContext::url('staff.calendar.modal', ['kind' => 'new', 'date' => $date]) }}" data-calendar-modal data-calendar-key="new"><x-booking-icon name="plus" /> New</a>
    </header>
    <div class="panel calendar-toolbar">
        <div class="actions">
            <a class="button secondary calendar-icon-button" href="{{ \App\Services\VenueContext::url('staff.calendar', ['date' => $day->subDay()->toDateString()]) }}" aria-label="Previous day"><x-booking-icon name="chevron-left" /></a>
            <form class="actions" method="get" action="{{ \App\Services\VenueContext::url('staff.calendar') }}">
                <label class="visually-hidden" for="calendar-date">Calendar date</label>
                <input id="calendar-date" name="date" type="date" value="{{ $date }}" required>
                <button class="secondary">Go</button>
            </form>
            <a class="button secondary calendar-icon-button" href="{{ \App\Services\VenueContext::url('staff.calendar', ['date' => $day->addDay()->toDateString()]) }}" aria-label="Next day"><x-booking-icon name="chevron-right" /></a>
            <a class="button secondary" href="{{ \App\Services\VenueContext::url('staff.calendar') }}">Today</a>
        </div>
        <div class="calendar-date">
            <strong>{{ $day->format('l, M j, Y') }}</strong>
            <span>{{ $hours && !$hours->closed ? $hours->opens_at.'–'.$hours->closes_at : 'Closed' }}</span>
            <button type="button" class="secondary calendar-icon-button" data-calendar-refresh aria-label="Refresh calendar"><x-booking-icon name="refresh-cw" /></button>
        </div>
    </div>
    <p class="calendar-toast" data-calendar-toast role="status" aria-live="polite" hidden></p>
    @include('staff.calendar.grid')

    <dialog id="calendar-dialog" class="calendar-dialog" aria-labelledby="calendar-modal-title"
            @if($initialModal) data-initial-url="{{ $initialModal }}" @endif>
        <div class="calendar-dialog-header">
            <span class="muted">Calendar</span>
            <button type="button" class="secondary calendar-icon-button" data-calendar-close aria-label="Close dialog"><x-booking-icon name="x" /></button>
        </div>
        <div class="calendar-dialog-body" data-calendar-modal-content>
            <h2 id="calendar-modal-title">{{ $initialTitle ?? 'Calendar details' }}</h2>
        </div>
    </dialog>
    @if($initialModal)
        <noscript><a class="button" href="{{ $initialModal }}">Continue to calendar form</a></noscript>
    @endif
    <script src="{{ asset('calendar.js') }}?v=1" defer></script>
@endsection
