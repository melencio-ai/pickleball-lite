@extends('layout')
@section('title', 'Booking payment')
@section('page-class', 'narrow')

@section('content')
    @php($open = !$booking->cancelled_at && !$booking->paid_at && !$booking->paymentExpired())
    <section class="panel confirmation">
        @if($booking->cancelled_at)
            <h1>Booking cancelled</h1>
        @elseif($booking->paid_at)
            <h1>Payment verified</h1>
            <p>Your court is booked.</p>
        @elseif($booking->paymentExpired())
            <h1>Payment hold expired</h1>
            <p>The court slot has been released. If you already paid, contact the venue in Messenger with your booking reference and receipt.</p>
        @else
            <h1>Pay by QR</h1>
            <p>Your court is held until {{ $booking->payment_expires_at->setTimezone($venue->timezone)->format('g:i A') }} ({{ $venue->timezone }}). The venue must verify payment before the hold ends.</p>
        @endif

        <dl class="receipt">
            <div><dt>Booking reference</dt><dd class="ref">{{ strtoupper($booking->reference) }}</dd></div>
            <div><dt>Court</dt><dd>{{ $booking->court->name }}</dd></div>
            <div><dt>Date and time</dt><dd>{{ $booking->starts_at->setTimezone($venue->timezone)->format('M j, Y · g:i A') }} – {{ $booking->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</dd></div>
            <div><dt>Total</dt><dd>₱{{ number_format($booking->total / 100, 2) }}</dd></div>
        </dl>

        @if($open)
            <canvas class="payment-qr" data-payment-qr="{{ $booking->payment_qr_payload }}" data-seconds-left="{{ max(0, $booking->payment_expires_at->getTimestamp() - now()->getTimestamp()) }}" aria-label="Booking payment QR code"></canvas>
            <div class="actions">
                <a class="button secondary" data-payment-download download="booking-payment-qr.png" hidden>Save QR image</a>
            </div>
            <noscript><p>Enable JavaScript to display the payment QR code.</p></noscript>
            <p class="payment-instructions">Scan the QR with your participating bank or wallet. Check the recipient and total before paying, then send your receipt and booking reference in Messenger.</p>
            <script src="{{ asset('vendor/qrcode-generator-1.4.4.js') }}" defer></script>
            <script src="{{ asset('payment-qr.js') }}" defer></script>
        @endif

        <div class="actions">
            @if(!$booking->paid_at || $booking->cancelled_at)
                <a class="button" href="{{ $booking->payment_messenger_url }}" target="_blank" rel="noopener noreferrer">{{ $open ? 'Send receipt in Messenger' : 'Contact venue in Messenger' }}</a>
            @endif
            @if($open)
                <a class="button secondary" href="{{ route('book.payment', $booking->payment_token) }}">Refresh status</a>
            @endif
        </div>
        @if($booking->cancellation_policy)
            <div class="booking-policy"><strong>Cancellation policy</strong><p>{{ $booking->cancellation_policy }}</p></div>
        @endif
    </section>
@endsection
