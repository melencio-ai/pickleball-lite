@extends('layout')
@section('title', 'Book a court')

@section('content')
    <header class="booking-banner">
        <div>
            <p class="venue-name">{{ $venue->name }}</p>
            <h1>Book a court</h1>
            @if($venue->address)
                <p>{{ $venue->address }}</p>
            @endif
        </div>
        <span class="pay-label">Pay at venue</span>
    </header>

    <form class="panel filters" method="get" action="{{ route('book') }}">
        <label>Date
            <input name="date" type="date" min="{{ $today->toDateString() }}" max="{{ $lastDay->toDateString() }}" value="{{ $date }}" required>
        </label>
        <label>Duration
            <select name="duration">
                <option value="60" @selected($duration === 60)>1 hour</option>
                <option value="120" @selected($duration === 120)>2 hours</option>
            </select>
        </label>
        <button>Show courts</button>
    </form>

    @if($selection)
        <section class="panel checkout" id="booking-form" tabindex="-1">
            <div class="section-heading">
                <div>
                    <h2>Confirm your booking</h2>
                    <p>{{ $selection['court']->name }} · {{ $selection['slots'][$selectedStart] }} · {{ intdiv($duration, 60) }} {{ $duration === 60 ? 'hour' : 'hours' }}</p>
                </div>
                <strong class="price">₱{{ number_format($selection['total'] / 100, 2) }}</strong>
            </div>
            <form class="form-row" method="post" action="{{ route('book.store') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <input type="hidden" name="duration" value="{{ $duration }}">
                <input type="hidden" name="court_id" value="{{ $selection['court']->id }}">
                <input type="hidden" name="start" value="{{ $selectedStart }}">
                <input type="hidden" name="quoted_total" value="{{ $selection['total'] }}">
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
                    <h3>{{ $offer['court']->name }}</h3>
                    <p><strong>₱{{ number_format($offer['total'] / 100, 2) }}</strong> / {{ $duration === 60 ? 'hour' : '2 hours' }}</p>
                </div>
                @if($offer['slots'])
                    <div class="slots">
                        @foreach($offer['slots'] as $time => $label)
                            <a class="slot @if($selection && $selection['court']->id === $offer['court']->id && $selectedStart === $time) selected @endif"
                                href="{{ route('book', ['date' => $date, 'duration' => $duration, 'court' => $offer['court']->id, 'start' => $time]) }}#booking-form"
                                aria-label="{{ $offer['court']->name }}, {{ $label }}">{{ $label }}</a>
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
