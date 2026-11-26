@extends('layout')
@section('title', 'Book a court')

@section('content')
    <header class="booking-banner venue-banner" style="--venue-brand: {{ $venue->brand_color ?? '#155542' }}">
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
        <span class="pay-label">Pay at venue</span>
    </header>
    @if($venue->map_url || $venue->messenger_url)
        <nav class="venue-links" aria-label="Venue information">
            @if($venue->map_url)<a href="{{ $venue->map_url }}" rel="noopener noreferrer">Get directions</a>@endif
            @if($venue->messenger_url)<a href="{{ $venue->messenger_url }}" rel="noopener noreferrer">Message the venue</a>@endif
        </nav>
    @endif

    <form class="panel filters" method="get" action="{{ route('book') }}">
        <label>Date
            <input name="date" type="date" min="{{ $today->toDateString() }}" max="{{ $lastDay->toDateString() }}" value="{{ $date }}" required>
        </label>
        <label>Duration
            <select name="duration">
                @foreach($venue->durations() as $minutes)
                    <option value="{{ $minutes }}" @selected($duration === $minutes)>{{ $minutes === 60 ? '1 hour' : ($minutes === 120 ? '2 hours' : $minutes.' minutes') }}</option>
                @endforeach
            </select>
        </label>
        <button>Show courts</button>
    </form>

    @if($selection)
        <section class="panel checkout" id="booking-form" tabindex="-1">
            <div class="section-heading">
                <div>
                    <h2>Confirm your booking</h2>
                    <p>{{ $selection['court']->name }} · {{ $selection['slots'][$selectedStart]['label'] }} · {{ $duration }} minutes</p>
                </div>
                <strong class="price">₱{{ number_format($selection['slots'][$selectedStart]['total'] / 100, 2) }}</strong>
            </div>
            @if($venue->cancellation_policy)
                <div class="booking-policy"><strong>Cancellation policy</strong><p>{{ $venue->cancellation_policy }}</p></div>
            @endif
            <form class="form-row" method="post" action="{{ route('book.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="duration" value="{{ $duration }}">
                <input type="hidden" name="court_id" value="{{ $selection['court']->id }}">
                <input type="hidden" name="start" value="{{ $selectedStart }}">
                <input type="hidden" name="quoted_total" value="{{ $selection['slots'][$selectedStart]['total'] }}">
                <label>Full name<input name="customer_name" value="{{ is_string(old('customer_name')) ? old('customer_name') : '' }}" maxlength="100" autocomplete="name" required></label>
                <label>Mobile number<input name="customer_phone" type="tel" value="{{ is_string(old('customer_phone')) ? old('customer_phone') : '' }}" maxlength="32" autocomplete="tel" required></label>
                <button>Confirm booking</button>
            </form>
        </section>
    @endif

    <section class="available-courts" aria-labelledby="available-title">
        <div class="section-heading">
            <h2 id="available-title">Court times</h2>
            <p>{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }} · {{ $venue->timezone }}</p>
        </div>
        @if(!$selection && request()->has('start') && !$errors->any())
            <p class="notice">That slot is unavailable. Choose another time below.</p>
        @endif
        @forelse($courts as $offer)
            <article class="panel court-card">
                <div class="section-heading">
                    <h3>{{ $offer['court']->name }} @if($offer['court']->surface_type)<span class="status-chip">{{ ucfirst($offer['court']->surface_type) }}</span>@endif</h3>
                    @if($offer['slots'])
                        @php($prices = collect($offer['slots'])->pluck('total')->unique())
                        <p>@if($prices->count() > 1)From @endif<strong>₱{{ number_format($prices->min() / 100, 2) }}</strong></p>
                    @endif
                </div>
                @if($offer['slots'])
                    <div class="slots">
                        @foreach($offer['slots'] as $time => $slot)
                            <a class="slot @if($selection && $selection['court']->id === $offer['court']->id && $selectedStart === $time) selected @endif"
                                href="{{ route('book', ['date' => $date, 'duration' => $duration, 'court' => $offer['court']->id, 'start' => $time]) }}#booking-form"
                                aria-label="{{ $offer['court']->name }}, {{ $slot['label'] }}, ₱{{ number_format($slot['total'] / 100, 2) }}">
                                {{ $slot['label'] }}
                                @if($prices->count() > 1)<small>₱{{ number_format($slot['total'] / 100, 2) }}</small>@endif
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="muted">No available times for this duration.</p>
                @endif
            </article>
        @empty
            <div class="panel empty">No court times are available on this date. Try another date.</div>
        @endforelse
    </section>
    @if($venue->phone)
        <p class="contact">To change or cancel a booking, call <a href="tel:{{ preg_replace('/[^+0-9]/', '', $venue->phone) }}">{{ $venue->phone }}</a>.</p>
    @endif
@endsection
