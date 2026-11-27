@props(['venue','logoVersion'=>null])
@php
    $home = \App\Services\VenueContext::url('landing',venue:$venue);
    $booking = \App\Services\VenueContext::url('book',venue:$venue);
    $openPlay = \App\Services\VenueContext::url('open-play',venue:$venue);
@endphp
<header class="venue-site-header public-booking-header">
    <div class="site-container site-navigation">
        <x-venue-site-brand :venue="$venue" :logo-version="$logoVersion"/>
        <nav aria-label="Venue navigation">
            <a href="{{ $home }}">Home</a>
            <a href="{{ $home }}#schedule">Schedule</a>
            <a href="{{ $home }}#rates">Rates</a>
            <a href="{{ $openPlay }}" @if(\App\Services\VenueContext::is('open-play*')) aria-current="page" @endif>Open Play</a>
            <a href="{{ $home }}#contact">Contact</a>
        </nav>
        <a class="site-button site-primary" href="{{ $booking }}" @if(\App\Services\VenueContext::is('book') || \App\Services\VenueContext::is('book.*')) aria-current="page" @endif>Book now <x-booking-icon name="chevron-right"/></a>
    </div>
</header>
