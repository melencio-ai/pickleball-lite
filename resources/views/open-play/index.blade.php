@extends('layout')
@section('title', 'Join Open Play')
@section('page-class', 'narrow booking-page')

@section('content')
    <header class="booking-banner venue-banner compact-venue-banner">
        @if($media->has('cover'))
            <img class="venue-cover" src="{{ \App\Services\VenueContext::url('book.image', ['kind' => 'cover', 'version' => $media['cover']->version]) }}" alt="">
        @endif
        <div>
            <p class="venue-name">
                @if($media->has('logo'))
                    <img class="venue-logo" src="{{ \App\Services\VenueContext::url('book.image', ['kind' => 'logo', 'version' => $media['logo']->version]) }}" alt="">
                @endif
                {{ $venue->name }}
            </p>
            <h1>Join Open Play</h1>
            @if($venue->address)
                <p>{{ $venue->address }}</p>
            @endif
        </div>
        <span class="pay-label">Pay by QR</span>
    </header>
    @if($venue->map_url || $venue->messenger_url)
        <nav class="venue-links" aria-label="Venue information">
            @if($venue->map_url)<a href="{{ $venue->map_url }}" rel="noopener noreferrer"><x-booking-icon name="map-pin" /> Get directions</a>@endif
            @if($venue->messenger_url)<a href="{{ $venue->messenger_url }}" rel="noopener noreferrer"><x-booking-icon name="message-circle" /> Message the venue</a>@endif
        </nav>
    @endif



    <p class="booking-back"><a href="{{ \App\Services\VenueContext::url('book') }}">← Booking options</a> · <a href="{{ \App\Services\VenueContext::url('landing') }}">Venue website</a></p>
    <section class="panel survey-panel">
        <div class="section-heading">
            <p class="survey-step">Step {{ $step }} of 4</p>
            @if($step > 1)
                <a class="survey-back" href="{{ \App\Services\VenueContext::url('open-play', $step === 3 ? ['date' => $date] : []) }}">Back</a>
            @endif
        </div>
        <ol class="survey-progress open-play-progress" aria-label="Registration progress">
            @for($i = 1; $i <= 4; $i++)
                <li class="{{ $i <= $step ? 'complete' : '' }}" @if($i === $step) aria-current="step" @endif><span class="visually-hidden">Step {{ $i }}</span></li>
            @endfor
        </ol>
        @if($step > 1)
            <p class="survey-summary">{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }}</p>
        @endif
        @if(!$venue->qr_payments_enabled || !$venue->payment_qr_base || !$venue->payment_messenger_url)
            <p class="notice">Online Open Play registration is not available yet. Contact the venue for help.</p>
        @endif
        @if($step === 1)
            <h2 class="booking-icon-label"><x-booking-icon name="calendar-days" /> When do you want to play?</h2>
            <div class="survey-choices">
                @forelse($dates as $day => $offers)
                    <a class="survey-choice" href="{{ \App\Services\VenueContext::url('open-play', ['date' => $day]) }}">
                        <span><strong>{{ \Carbon\CarbonImmutable::parse($day)->format('D, M j') }}</strong><small>{{ $offers->count() }} {{ $offers->count() === 1 ? 'session' : 'sessions' }}</small></span>
                        <span>{{ $offers->sum(fn ($offer) => $offer->spotsLeft()) }} spots</span>
                    </a>
                @empty
                    <p class="notice">No upcoming Open Play sessions yet. Check back soon.</p>
                @endforelse
            </div>
        @elseif($step === 2)
            <h2 class="booking-icon-label"><x-booking-icon name="users" /> Which session would you like to join?</h2>
            @forelse($sessions as $offer)
                <article class="open-play-card">
                    <div class="section-heading"><h3>{{ $offer->name }}</h3><span class="status-chip {{ $offer->spotsLeft() ? 'paid' : 'unpaid' }}">{{ $offer->spotsLeft() ? $offer->spotsLeft().($offer->spotsLeft() === 1 ? ' spot left' : ' spots left') : 'Full' }}</span></div>
                    <p class="muted">{{ $offer->starts_at->setTimezone($venue->timezone)->format('g:i A') }} – {{ $offer->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</p>
                    <p class="muted">{{ $offer->courts_count }} {{ $offer->courts_count === 1 ? 'court' : 'courts' }} · {{ $offer->levelLabel() }}</p>
                    <progress class="open-play-capacity" value="{{ $offer->capacity - $offer->spotsLeft() }}" max="{{ $offer->capacity }}" aria-label="Spots taken"></progress>
                    <div class="section-heading"><span class="muted">{{ $offer->capacity - $offer->spotsLeft() }} of {{ $offer->capacity }} spots taken</span><strong>₱{{ number_format($offer->price / 100, 2) }}/player</strong></div>
                    @if($offer->spotsLeft() && $venue->qr_payments_enabled && $venue->payment_qr_base && $venue->payment_messenger_url)
                        <a class="button" href="{{ \App\Services\VenueContext::url('open-play', ['date' => $date, 'session' => $offer->id]) }}">Join session</a>
                    @endif
                </article>
            @empty
                <p class="notice">No sessions for this date. Pick another day.</p>
            @endforelse
        @else
            <h2 class="booking-icon-label"><x-booking-icon name="user-round" /> Who is joining?</h2>
            <div class="open-play-card">
                <h3>{{ $session->name }}</h3>
                <p class="muted">{{ $session->starts_at->setTimezone($venue->timezone)->format('g:i A') }} – {{ $session->ends_at->setTimezone($venue->timezone)->format('g:i A') }} · {{ $session->levelLabel() }}</p>
                <strong>₱{{ number_format($session->price / 100, 2) }}/player</strong>
            </div>
            @if($session->spotsLeft() && $venue->qr_payments_enabled && $venue->payment_qr_base && $venue->payment_messenger_url)
                <form method="post" action="{{ \App\Services\VenueContext::url('open-play.join', $session->id) }}" class="form-stack survey-form" data-booking-form>
                    @csrf
                    <input type="hidden" name="quoted_total" value="{{ $session->price }}">
                    <label>Full name<input name="customer_name" value="{{ is_string(old('customer_name')) ? old('customer_name') : '' }}" maxlength="100" autocomplete="name" required></label>
                    <label>Mobile number<input name="customer_phone" type="tel" value="{{ is_string(old('customer_phone')) ? old('customer_phone') : '' }}" maxlength="32" autocomplete="tel" required></label>
                    <p class="muted">One player per registration. Your spot will be held while you pay.</p>
                    @php($legal = \App\Services\LegalPolicies::context($venue))
                    <details class="compact-policy"><summary>Cancellation policy</summary><p>{{ $legal['policy'] }}</p></details>
                    <x-legal-acceptance :legal="$legal" :venue="$venue" />
                    <button class="booking-button" data-booking-submit><x-booking-icon name="qr-code" /> Continue to payment</button>
                </form>
            @else
                <p class="notice">This session is full or online registration is unavailable. Choose another session or contact the venue.</p>
            @endif
        @endif
    </section>
    <script src="{{ asset('booking-survey.js') }}" defer></script>
@endsection
