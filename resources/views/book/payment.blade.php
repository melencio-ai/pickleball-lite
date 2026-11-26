@extends('layout')
@section('title', 'Booking payment')
@section('page-class', 'narrow booking-page')

@section('content')
    @php
        $open = $status === 'pending';
        $ref = strtoupper($booking->reference);
        $starts = $booking->starts_at->setTimezone($venue->timezone);
        $ends = $booking->ends_at->setTimezone($venue->timezone);
    @endphp
    <section class="panel confirmation compact-payment">
        <p class="muted survey-step">Step 6 of 6 · Pay and confirm</p>
        @if($status === 'cancelled')
            <h1><x-booking-icon name="circle-alert" /> Booking cancelled</h1>
        @elseif($status === 'confirmed')
            <h1><x-booking-icon name="circle-check" /> Your court is booked!</h1>
            <p>Payment verified. See you on the court!</p>
        @elseif($status === 'expired')
            <h1><x-booking-icon name="circle-alert" /> Payment hold expired</h1>
            <p>The court was released. If you already paid, send your receipt and booking reference in Messenger.</p>
        @else
            <div class="section-heading payment-title">
                <h1>Ready to book your court?</h1>
                <strong class="price">₱{{ number_format($booking->total / 100, 2) }}</strong>
            </div>
            @php($secondsLeft = max(0, $booking->payment_expires_at->getTimestamp() - now()->getTimestamp()))
            <div class="payment-hold" data-payment-hold data-seconds-left="{{ $secondsLeft }}" data-status-url="{{ route('book.payment.status', $booking->payment_token) }}">
                <span class="booking-icon-label" id="payment-hold-label"><x-booking-icon name="timer" /> Court held for</span>
                <strong class="hold-countdown" data-hold-countdown role="timer" aria-labelledby="payment-hold-label">{{ sprintf('%02d:%02d', intdiv($secondsLeft, 60), $secondsLeft % 60) }}</strong>
                <span data-hold-expired role="status" hidden>Hold ended. Checking booking status…</span>
            </div>

            <div data-payment-instructions>
                <div class="payment-qr-block">
                    <canvas class="payment-qr" data-payment-qr="{{ $booking->payment_qr_payload }}" aria-label="Booking payment QR code"></canvas>
                    <a class="button secondary" data-payment-download download="booking-payment-qr.png" hidden>
                        <x-booking-icon name="download" /> Save QR image
                    </a>
                </div>
                <noscript><p>Enable JavaScript to display the QR and live countdown.</p></noscript>
                <ol class="payment-steps">
                    <li><strong>Pay using the QR</strong><span>Check the name and amount in your bank or wallet.</span></li>
                    <li><strong>Send your receipt in Messenger</strong><span>Include the booking reference so the venue can verify payment.</span></li>
                </ol>
            </div>
            <script src="{{ asset('vendor/qrcode-generator-1.4.4.js') }}" defer></script>
        @endif

        <div class="payment-reference">
            <div><span class="muted">Booking reference</span><strong class="ref" data-booking-reference>{{ $ref }}</strong></div>
            <button type="button" class="secondary copy-reference" data-copy-reference aria-label="Copy booking reference" hidden><x-booking-icon name="copy" /></button>
        </div>
        <p class="muted copy-feedback" data-copy-feedback role="status"></p>

        <div class="actions payment-actions">
            @if($status !== 'confirmed')
                <a class="button" href="{{ $booking->payment_messenger_url }}" target="_blank" rel="noopener noreferrer">
                    <x-booking-icon name="message-circle" /> {{ $open ? 'Send receipt in Messenger' : 'Contact venue in Messenger' }}
                </a>
            @endif
            @if($open)
                <a class="button secondary" href="{{ route('book.payment', $booking->payment_token) }}">Refresh status</a>
            @elseif(in_array($status, ['cancelled', 'expired'], true))
                <a class="button secondary" href="{{ route('book', ['date' => $starts->toDateString(), 'duration' => (int) $booking->starts_at->diffInMinutes($booking->ends_at)]) }}">Pick another time</a>
            @endif
        </div>

        <p class="payment-booking-summary">{{ $booking->court->name }} · {{ $starts->format('D, M j · g:i A') }}–{{ $ends->format('g:i A') }}
            @unless($open) · ₱{{ number_format($booking->total / 100, 2) }} @endunless
        </p>
        @if($booking->cancellation_policy)
            <details class="compact-policy">
                <summary>Cancellation policy <x-booking-icon name="chevron-down" /></summary>
                <p>{{ $booking->cancellation_policy }}</p>
            </details>
        @endif
    </section>
    <script src="{{ asset('payment-qr.js') }}?v=3" defer></script>
@endsection
