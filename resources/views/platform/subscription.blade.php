@extends('layout')
@section('title','My subscription')
@section('content')
@php
$end=$subscription?->trial_ends_at ? \Carbon\CarbonImmutable::parse($subscription->trial_ends_at) : null;
$days=$end && $end->isFuture() ? (int)ceil(now()->diffInSeconds($end)/86400) : 0;
$status=$subscription?->status ?? 'onboarding';
if($status==='trial' && !$days) $status='trial_expired';
if($status==='active' && \App\Services\SubscriptionPlans::timed($subscription) && !\App\Services\TrialAccess::allowed($venue)) $status='overdue';
@endphp
<header class="section-heading page-heading"><div><h1>My subscription</h1><p class="muted">{{ $venue->name }}</p></div><span class="status-chip">{{ ucfirst(str_replace('_',' ',$status)) }}</span></header>
<div class="settings-grid">
<section class="panel form-stack">
    @if($status==='trial')
        <h2>{{ $days }} {{ $days===1?'day':'days' }} remaining</h2>
        <p>Trial ends {{ $end->setTimezone($venue->timezone)->format('M j, Y g:i A') }}.</p>
        @if($canExtend)<div class="notice"><strong>Need more time?</strong><p>Extend your free trial by 15 days. No charge.</p><form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.extend') }}" data-app-form>@csrf<input type="hidden" name="extension_count" value="{{ $subscription->trial_extensions_count }}"><button data-app-submit>Extend free trial</button></form></div>@endif
    @elseif($status==='trial_expired')
        <h2>Your free trial has ended</h2><p>New reservations are unavailable. Existing bookings, payments and account records remain accessible.</p>
    @elseif($status==='active')
        <h2>{{ \App\Services\SubscriptionPlans::annual($subscription) ? 'Yearly subscription' : ($subscription->reward_managed ? 'Subscription (reward-managed)' : 'Paid subscription') }}</h2><p>Next renewal: {{ $subscription->renewal_date ?? 'Contact Pickle Lite' }}.</p>@if(\App\Services\SubscriptionPlans::annual($subscription))<p class="muted">Standard yearly renewal: ₱10,000. No automatic charge.</p>@endif
    @else
        <h2>{{ ucfirst(str_replace('_',' ',$status)) }}</h2><p>Contact Pickle Lite for assistance with your subscription.</p>
    @endif
    @if($end)<p class="muted">Original trial start: {{ \Carbon\CarbonImmutable::parse($subscription->trial_started_at)->setTimezone($venue->timezone)->format('M j, Y') }}</p>@endif
</section>
<section class="panel form-stack">
    <h2>Choose your subscription</h2>
    <p>Includes booking website, court reservations, Open Play and management tools. Free setup. No automatic charges.</p>
    @if($pending)
        <section class="panel form-stack" id="subscription-payment">
            <h3>{{ \App\Services\SubscriptionPlans::LABELS[$pending->plan] }} payment</h3>
            <p class="price">₱{{ number_format($pending->amount/100,2) }}</p>
            <p>Payment reference: <strong data-booking-reference>{{ $pending->reference }}</strong></p>
            <button type="button" class="secondary" data-copy-reference hidden>Copy reference</button><p class="muted" data-copy-feedback role="status"></p>
            @if($pending->status==='awaiting_payment')
                <div class="payment-qr-block">
                    <canvas class="payment-qr" data-payment-qr="{{ $qr }}" aria-label="Pickle Lite subscription payment QR code"></canvas>
                    <a class="button secondary" data-payment-download download="picklelite-subscription-qr.png" hidden>Save QR image</a>
                </div>
                <noscript><p>Enable JavaScript to display the payment QR.</p></noscript>
                <ol class="payment-steps"><li><strong>Scan and pay</strong><span>Check the recipient and exact amount in your bank or wallet.</span></li><li><strong>Send your receipt in Messenger</strong><span>Include the payment reference above and your venue name.</span></li></ol>
                <x-messenger-link class="button" :url="\App\Services\MessengerLinks::PLATFORM"><x-workspace-icon name="mail"/> Send proof of payment</x-messenger-link>
                <form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.payment.action',['payment'=>$pending->id,'action'=>'submit']) }}" data-app-form>@csrf<button data-app-submit class="secondary">I have sent my proof</button></form>
                <details><summary>Cancel unpaid request</summary><form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.payment.action',['payment'=>$pending->id,'action'=>'cancel']) }}" data-app-form>@csrf<label><input type="checkbox" name="not_paid" value="1" required> I have not paid this QR.</label><button data-app-submit class="secondary">Cancel unpaid request</button></form></details>
                <script src="{{ asset('vendor/qrcode-generator-1.4.4.js') }}" defer></script>
                <script src="{{ asset('payment-qr.js') }}?v=4" defer></script>
            @else
                <p class="notice">Awaiting verification. Your subscription will extend only after payment is confirmed.</p>
                <x-messenger-link class="button secondary" :url="\App\Services\MessengerLinks::PLATFORM">Follow up in Messenger</x-messenger-link>
            @endif
        </section>
    @elseif($lastRequest?->status==='rejected')
        <p class="notice">Your last payment request was rejected: {{ $lastRequest->review_note }}</p>
    @endif
    @if(!$qrEnabled && !$pending && $subscription?->upgrade_requested_at)
        <div class="notice success">Requested: {{ \App\Services\SubscriptionPlans::LABELS[$subscription->requested_plan ?? 'monthly'] }}. Message Pickle Lite to arrange payment.</div>
        <x-messenger-link class="button secondary" :url="\App\Services\MessengerLinks::PLATFORM"><x-workspace-icon name="mail"/> Arrange payment in Messenger</x-messenger-link>
    @endif
    @if(!$pending && !in_array($status,['cancelled','suspended'],true))
        @if($promo)
            <section class="panel form-stack" id="special-offer">
                <h3>Get 50% off your first year!</h3><p><s>₱10,000</s> <strong>₱5,000 for your first year</strong>. Save ₱5,000. Subsequent yearly renewals are ₱10,000.</p>
                <form method="post" action="{{ \App\Services\VenueContext::url($qrEnabled?'settings.subscription.checkout':'settings.subscription.upgrade') }}" data-app-form>@csrf<input type="hidden" name="plan" value="yearly_promo"><button data-app-submit @disabled(!$qrEnabled && $subscription?->requested_plan==='yearly_promo')>{{ $qrEnabled?'Claim offer and pay':($subscription?->requested_plan==='yearly_promo'?'Offer claimed — arrange payment':'Claim offer') }}</button></form>
            </section>
        @endif
        <section class="panel form-stack"><h3>Monthly</h3><p><strong>₱1,000</strong> per month</p><form method="post" action="{{ \App\Services\VenueContext::url($qrEnabled?'settings.subscription.checkout':'settings.subscription.upgrade') }}" data-app-form>@csrf<input type="hidden" name="plan" value="monthly"><button class="secondary" data-app-submit @disabled(!$qrEnabled && $subscription?->requested_plan==='monthly')>Choose monthly</button></form></section>
        <section class="panel form-stack"><h3>Yearly</h3><p><strong>₱10,000</strong> per year · Save ₱2,000</p><form method="post" action="{{ \App\Services\VenueContext::url($qrEnabled?'settings.subscription.checkout':'settings.subscription.upgrade') }}" data-app-form>@csrf<input type="hidden" name="plan" value="yearly"><button class="secondary" data-app-submit @disabled(!$qrEnabled && $subscription?->requested_plan==='yearly')>Choose yearly</button></form></section>
    @endif
    @if(\App\Services\VenueContext::onApp() && \App\Services\PlatformRewards::visible($venue,$subscription))
        <a class="button secondary" href="{{ \App\Services\VenueContext::url('settings.rewards') }}"><x-workspace-icon name="gift"/> View subscription rewards</a>
    @endif
    <a href="{{ route('product.legal.show',['document'=>'subscription-terms']) }}" target="_blank" rel="noopener">Subscription Terms</a>
</section>
</div>
<section class="panel tenant-panel"><h2>Payment history</h2><div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Received</th><th>Plan</th><th>Reference</th><th>Amount</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->received_on }}</td><td>{{ \App\Services\SubscriptionPlans::LABELS[$payment->kind] ?? ucfirst($payment->kind) }}</td><td>{{ $payment->reference }}</td><td>₱{{ number_format($payment->amount/100,2) }}</td></tr>@empty<tr><td colspan="4">No verified payments yet.</td></tr>@endforelse</tbody></table></div>@if($subscription){{ $payments->links() }}@endif</section>
@endsection
