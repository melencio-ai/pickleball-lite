@extends('layout')
@section('title', 'Booking confirmed')
@section('page-class', 'narrow')

@section('content')
    <section class="panel confirmation">
        <p class="status-chip paid">Confirmed</p>
        <h1>Your court is booked</h1>
        <dl class="receipt">
            <div><dt>Booking reference</dt><dd class="ref">{{ $confirmation['reference'] }}</dd></div>
            <div><dt>Court</dt><dd>{{ $confirmation['court'] }}</dd></div>
            <div><dt>Date</dt><dd>{{ $confirmation['date'] }}</dd></div>
            <div><dt>Time</dt><dd>{{ $confirmation['time'] }}</dd></div>
            <div><dt>Due at venue</dt><dd>₱{{ number_format($confirmation['total'] / 100, 2) }}</dd></div>
        </dl>
        <a class="button" href="{{ route('book') }}">Book another court</a>
    </section>
@endsection
