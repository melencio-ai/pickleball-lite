@extends('layout')
@section('title','Booking and cancellation policy')
@section('content')
<div class="legal-public">
    <header class="section-heading"><div><h1>Booking and cancellation policy</h1><p class="muted">{{ $venue->name }}</p></div><a class="button secondary" href="{{ \App\Services\VenueContext::url('landing',venue:$venue) }}">Venue website</a></header>
    <section class="panel form-stack">
        <h2>Venue details</h2><p><strong>{{ $venue->name }}</strong><br>{{ $venue->address ?: 'Contact the venue for its address.' }}@if($venue->phone)<br><a href="tel:{{ preg_replace('/[^+0-9]/','',$venue->phone) }}">{{ $venue->phone }}</a>@endif</p>
        @if($venue->messenger_url || $venue->payment_messenger_url)<a href="{{ $venue->messenger_url ?: $venue->payment_messenger_url }}" rel="noopener noreferrer" target="_blank">Contact the venue in Messenger</a>@endif
        <h2>Reservations</h2><p>Check your selected court, date, time, duration and total before confirming. Open Play is subject to player capacity.</p>
        @if($venue->qr_payments_enabled)<h2>QR payments</h2><p>Pay the displayed amount to the venue merchant and send your receipt and reference in Messenger. Payment must be verified by the venue. Unpaid holds expire after 20 minutes or sooner if the booking starts. Contact the venue if payment arrives after a hold expires.</p>@endif
        <h2>Changes, cancellations and refunds</h2><div class="legal-prose">{{ $policy }}</div>
        <p class="muted">The policy recorded at the time of your reservation applies, subject to mandatory consumer rights. The venue can assist with changes, refunds and service complaints.</p>
    </section>
</div>
@endsection
