@extends('layout')
@section('title', 'Book a court')
@section('page-class', 'narrow booking-page')

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


    @php
        $startTimes = [];
        foreach ($courts as $offer) {
            foreach ($offer['slots'] as $time => $slot) {
                if (!isset($startTimes[$time])) {
                    $startTimes[$time] = ['label' => $slot['label'], 'count' => 0];
                }
                $startTimes[$time]['count']++;
            }
        }
        ksort($startTimes);
        $validStart = is_string($selectedStart) && isset($startTimes[$selectedStart]);
        $requestedStep = request()->query('step');
        $step = in_array($requestedStep, ['1', '2', '3', '4', '5'], true)
            ? (int) $requestedStep
            : ($selection ? 5 : ($validStart ? 4 : (request()->has('date') ? 3 : 1)));
        if ($step === 5 && !$selection) {
            $step = $validStart ? 4 : 3;
        }
        if ($step === 4 && !$validStart) {
            $step = 3;
        }
        $dateLabel = \Carbon\CarbonImmutable::parse($date)->format('D, M j');
        $durationLabel = fn ($minutes) => \App\Models\Venue::durationLabel($minutes);
        $titles = [1 => 'When do you want to play?', 2 => 'How long do you want to play?', 3 => 'What time do you want to start?', 4 => 'Which court do you prefer?', 5 => 'Who is this booking for?'];
        $icons = [1 => 'calendar-days', 2 => 'clock', 3 => 'timer', 4 => 'layout-grid', 5 => 'user-round'];
        $back = match ($step) {
            2 => ['step' => 1, 'date' => $date, 'duration' => $duration],
            3 => ['step' => 2, 'date' => $date, 'duration' => $duration],
            4 => ['step' => 3, 'date' => $date, 'duration' => $duration],
            5 => ['step' => 4, 'date' => $date, 'duration' => $duration, 'start' => $selectedStart],
            default => [],
        };
    @endphp

    <section class="panel survey-panel" aria-labelledby="survey-title">
        <div class="section-heading">
            <p class="survey-step">Step {{ $step }} of 6</p>
            @if($step > 1)
                <a class="survey-back" href="{{ route('book', $back) }}">Back</a>
            @endif
        </div>
        <ol class="survey-progress" aria-label="Booking progress">
            @for($i = 1; $i <= 6; $i++)
                <li class="{{ $i <= $step ? 'complete' : '' }}" @if($i === $step) aria-current="step" @endif>
                    <span class="visually-hidden">Step {{ $i }}</span>
                </li>
            @endfor
        </ol>
        @if($step > 1)
            <p class="survey-summary">{{ $dateLabel }} @if($step > 2) · {{ $durationLabel($duration) }} @endif
                @if($step > 3 && $validStart) · {{ $startTimes[$selectedStart]['label'] }} @endif
            </p>
        @endif
        <h2 id="survey-title" class="booking-icon-label"><x-booking-icon :name="$icons[$step]" /> {{ $titles[$step] }}</h2>

        @if($step !== 5)
            @include('book.errors')
            @if(request()->has('start') && !$selection && request()->has('court') && !$errors->any())
                <p class="notice booking-notice" role="status"><x-booking-icon name="circle-alert" /> That court is no longer available. Please choose another.</p>
            @endif
        @endif

        @if($step === 1)
            <p class="muted">Pick a date below.</p>
            <form method="get" action="{{ route('book') }}" class="form-stack survey-form">
                <input type="hidden" name="step" value="2">
                <input type="hidden" name="duration" value="{{ $duration }}">
                <label>Booking date
                    <input name="date" type="date" min="{{ $today->toDateString() }}" max="{{ $lastDay->toDateString() }}" value="{{ $date }}" required>
                </label>
                <button class="booking-button">Choose duration</button>
            </form>
        @elseif($step === 2)
            <p class="muted">Choose how much time you need.</p>
            <div class="survey-choices">
                @foreach($venue->durations() as $minutes)
                    <a class="survey-choice" href="{{ route('book', ['step' => 3, 'date' => $date, 'duration' => $minutes]) }}">
                        <x-booking-icon name="clock" /> <strong>{{ $durationLabel($minutes) }}</strong>
                    </a>
                @endforeach
            </div>
        @elseif($step === 3)
            <p class="muted">Pick a time from the available slots.</p>
            @if($startTimes)
                <div class="slots booking-slots survey-times">
                    @foreach($startTimes as $time => $slot)
                        <a class="slot" href="{{ route('book', ['step' => 4, 'date' => $date, 'duration' => $duration, 'start' => $time]) }}">
                            {{ $slot['label'] }}<small>{{ $slot['count'] }} {{ $slot['count'] === 1 ? 'court' : 'courts' }}</small>
                        </a>
                    @endforeach
                </div>
            @else
                <div class="notice survey-empty">No court times are available on this date for this duration.</div>
                <a class="button secondary" href="{{ route('book', ['step' => 1, 'date' => $date, 'duration' => $duration]) }}">Pick another date</a>
            @endif
        @elseif($step === 4)
            <p class="muted">Pick an available court. Prices are for your full booking.</p>
            <div class="survey-choices">
                @foreach($courts as $offer)
                    @if(isset($offer['slots'][$selectedStart]))
                        <a class="survey-choice" href="{{ route('book', ['step' => 5, 'date' => $date, 'duration' => $duration, 'start' => $selectedStart, 'court' => $offer['court']->id]) }}">
                            <span><strong>{{ $offer['court']->name }}</strong>
                                @if($offer['court']->surface_type)<small>{{ ucfirst($offer['court']->surface_type) }}</small>@endif
                            </span>
                            <strong class="survey-price">₱{{ number_format($offer['slots'][$selectedStart]['total'] / 100, 2) }}</strong>
                        </a>
                    @endif
                @endforeach
            </div>
        @else
            @include('book.details')
        @endif
    </section>
    @if($venue->phone)
        <p class="contact">To change or cancel a booking, call <a href="tel:{{ preg_replace('/[^+0-9]/', '', $venue->phone) }}">{{ $venue->phone }}</a>.</p>
    @endif
    <script src="{{ asset('booking-survey.js') }}" defer></script>
@endsection
