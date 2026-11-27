@extends('layout')
@section('title', 'Booking confirmed')
@section('page-class', 'narrow')

@section('content')
    <section class="panel confirmation">
        <p class="muted survey-step">Step 6 of 6 · Confirmed</p>
        <p class="status-chip paid">Confirmed</p>
        <h1><x-booking-icon name="circle-check" /> Your court is booked!</h1>
        <dl class="receipt">
            <div><dt>Booking reference</dt><dd class="ref">{{ $confirmation['reference'] }}</dd></div>
            <div><dt>Court</dt><dd>{{ $confirmation['court'] }}</dd></div>
            <div><dt>Date</dt><dd>{{ $confirmation['date'] }}</dd></div>
            <div><dt>Time</dt><dd>{{ $confirmation['time'] }}</dd></div>
            <div><dt>Due at venue</dt><dd>₱{{ number_format($confirmation['total'] / 100, 2) }}</dd></div>
        </dl>
        @if($confirmation['cancellation_policy'] ?? null)
            <div class="booking-policy"><strong>Cancellation policy</strong><p>{{ $confirmation['cancellation_policy'] }}</p></div>
        @endif
        <a class="button" href="{{ \App\Services\VenueContext::url('book') }}">Book another court</a>
    </section>
@endsection
