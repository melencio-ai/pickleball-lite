@extends('layout')
@section('title', 'Start booking')
@section('page-class', 'narrow booking-page')
@section('content')
    <header class="booking-banner venue-banner compact-venue-banner">
        @if($media->has('cover'))
            <img class="venue-cover" src="{{ \App\Services\VenueContext::url('book.image',['kind'=>'cover','version'=>$media['cover']->version]) }}" alt="">
        @endif
        <div>
            <p class="venue-name">
                @if($media->has('logo') && !\App\Services\VenueContext::onBooking())<img class="venue-logo" src="{{ \App\Services\VenueContext::url('book.image',['kind'=>'logo','version'=>$media['logo']->version]) }}" alt="">@endif
                {{ $venue->name }}
            </p>
            <h1>Let's play!</h1>
            @if($venue->address)<p>{{ $venue->address }}</p>@endif
        </div>
    </header>
    <p class="booking-back"><a href="{{ \App\Services\VenueContext::url('landing') }}">← Back to venue website</a></p>
    <section class="panel booking-types booking-type-menu">
        <h2>What would you like to book?</h2>
        <a class="survey-choice" href="{{ \App\Services\VenueContext::url('book',['step'=>1]) }}">
            <x-booking-icon name="layout-grid" />
            <span><strong>Book a court</strong><small>Reserve a court for your own group.</small></span>
            <x-booking-icon name="chevron-right" />
        </a>
        <a class="survey-choice" href="{{ \App\Services\VenueContext::url('open-play') }}">
            <x-booking-icon name="users" />
            <span><strong>Join Open Play</strong><small>Meet and play with other players.</small></span>
            <x-booking-icon name="chevron-right" />
        </a>
    </section>
@endsection
