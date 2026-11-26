@extends('layout')
@section('title', 'Book a court')
@section('page-class', 'booking-page')

@section('content')
    <header class="booking-banner venue-banner compact-venue-banner" style="--venue-brand: {{ $venue->brand_color ?? '#155542' }}">
        @if($media->has('cover'))
            <img class="venue-cover" src="{{ route('book.image', ['kind' => 'cover', 'version' => $media['cover']->version]) }}" alt="">
        @endif
        <div>
            <p class="venue-name">
                @if($media->has('logo'))
                    <img class="venue-logo" src="{{ route('book.image', ['kind' => 'logo', 'version' => $media['logo']->version]) }}" alt="">
                @endif
                {{ $venue->name }}
            </p>
            <h1>Book a court</h1>
            @if($venue->address)
                <p>{{ $venue->address }}</p>
            @endif
        </div>
        <span class="pay-label">{{ $venue->qr_payments_enabled ? 'Pay by QR' : 'Pay at venue' }}</span>
    </header>
    @if($venue->map_url || $venue->messenger_url)
        <nav class="venue-links" aria-label="Venue information">
            @if($venue->map_url)<a href="{{ $venue->map_url }}" rel="noopener noreferrer"><x-booking-icon name="map-pin" /> Get directions</a>@endif
            @if($venue->messenger_url)<a href="{{ $venue->messenger_url }}" rel="noopener noreferrer"><x-booking-icon name="message-circle" /> Message the venue</a>@endif
        </nav>
    @endif

    <form class="panel filters booking-filters" method="get" action="{{ route('book') }}" data-booking-filters>
        <label><span class="booking-icon-label"><x-booking-icon name="calendar-days" /> Date</span>
            <input name="date" type="date" min="{{ $today->toDateString() }}" max="{{ $lastDay->toDateString() }}" value="{{ $date }}" required>
        </label>
        <label><span class="booking-icon-label"><x-booking-icon name="clock" /> Duration</span>
            <select name="duration">
                @foreach($venue->durations() as $minutes)
                    <option value="{{ $minutes }}" @selected($duration === $minutes)>{{ $minutes === 60 ? '1 hour' : ($minutes === 120 ? '2 hours' : $minutes.' minutes') }}</option>
                @endforeach
            </select>
        </label>
        <button data-filter-submit>Show courts</button>
    </form>

    <p class="visually-hidden" role="status" data-booking-feedback></p>
    @if(!$selection && $errors->any())
        <template data-booking-draft>
            <input name="customer_name" value="{{ is_string(old('customer_name')) ? old('customer_name') : '' }}">
            <input name="customer_phone" value="{{ is_string(old('customer_phone')) ? old('customer_phone') : '' }}">
        </template>
    @endif
    <section class="available-courts" aria-labelledby="available-title" data-booking-results>
        <div class="section-heading">
            <h2 id="available-title" tabindex="-1">Court times</h2>
            <p>{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }} · {{ $venue->timezone }}</p>
        </div>
        @if(!$selection)
            @include('book.errors')
            @if(request()->has('start') && !$errors->any())
                <p class="notice booking-notice" role="status"><x-booking-icon name="circle-alert" /> That slot is unavailable. Choose another time below.</p>
            @endif
        @endif
        @forelse($courts as $offer)
            <article class="panel court-card compact-court-card">
                <div class="section-heading">
                    <h3>{{ $offer['court']->name }} @if($offer['court']->surface_type)<span class="status-chip">{{ ucfirst($offer['court']->surface_type) }}</span>@endif</h3>
                    @if($offer['slots'])
                        @php($prices = collect($offer['slots'])->pluck('total')->unique())
                        <p>@if($prices->count() > 1)From @endif<strong>₱{{ number_format($prices->min() / 100, 2) }}</strong></p>
                    @endif
                </div>
                @if($offer['slots'])
                    <div class="slots booking-slots">
                        @foreach($offer['slots'] as $time => $slot)
                            <a class="slot @if($selection && $selection['court']->id === $offer['court']->id && $selectedStart === $time) selected @endif"
                                href="{{ route('book', ['date' => $date, 'duration' => $duration, 'court' => $offer['court']->id, 'start' => $time]) }}#booking-form"
                                data-booking-slot @if($selection && $selection['court']->id === $offer['court']->id && $selectedStart === $time) aria-current="true" @endif
                                aria-label="{{ $offer['court']->name }}, {{ $slot['label'] }}, ₱{{ number_format($slot['total'] / 100, 2) }}">
                                {{ $slot['label'] }}
                                @if($prices->count() > 1)<small>₱{{ number_format($slot['total'] / 100, 2) }}</small>@endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No available times for this duration.</p>
                @endif
                @if($selection && $selection['court']->id === $offer['court']->id)
                    @include('book.details')
                @endif
            </article>
        @empty
            <div class="panel empty">No court times are available on this date. Try another date.</div>
        @endforelse
    </section>
    @if($venue->phone)
        <p class="contact">To change or cancel a booking, call <a href="tel:{{ preg_replace('/[^+0-9]/', '', $venue->phone) }}">{{ $venue->phone }}</a>.</p>
    @endif
    <script src="{{ asset('booking.js') }}" defer></script>
@endsection
