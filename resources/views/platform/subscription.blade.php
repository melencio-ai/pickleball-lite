@extends('layout')
@section('title','My subscription')
@section('content')
@php
$end=$subscription?->trial_ends_at ? \Carbon\CarbonImmutable::parse($subscription->trial_ends_at) : null;
$days=$end && $end->isFuture() ? (int)ceil(now()->diffInSeconds($end)/86400) : 0;
$status=$subscription?->status ?? 'onboarding';
if($status==='trial' && !$days) $status='trial_expired';
if($status==='active' && ($subscription?->reward_managed || \App\Services\SubscriptionPlans::annual($subscription)) && !\App\Services\TrialAccess::allowed($venue)) $status='overdue';
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
    @if($subscription?->upgrade_requested_at)
        <div class="notice success">Requested: {{ \App\Services\SubscriptionPlans::LABELS[$subscription->requested_plan ?? 'monthly'] }}. Message Pickle Lite to arrange payment.</div>
        <x-messenger-link class="button secondary" :url="\App\Services\MessengerLinks::PLATFORM"><x-workspace-icon name="mail"/> Arrange payment in Messenger</x-messenger-link>
    @endif
    @if(!in_array($status,['cancelled','suspended'],true))
        @if($promo)
            <section class="panel form-stack" id="special-offer">
                <h3>Get 50% off your first year!</h3><p><s>₱10,000</s> <strong>₱5,000 for your first year</strong>. Save ₱5,000. Subsequent yearly renewals are ₱10,000.</p>
                <form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.upgrade') }}" data-app-form>@csrf<input type="hidden" name="plan" value="yearly_promo"><button data-app-submit @disabled($subscription?->requested_plan==='yearly_promo')>{{ $subscription?->requested_plan==='yearly_promo'?'Offer claimed — arrange payment':'Claim offer' }}</button></form>
            </section>
        @endif
        <section class="panel form-stack"><h3>Monthly</h3><p><strong>₱1,000</strong> per month</p><form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.upgrade') }}" data-app-form>@csrf<input type="hidden" name="plan" value="monthly"><button class="secondary" data-app-submit @disabled($subscription?->requested_plan==='monthly')>Choose monthly</button></form></section>
        <section class="panel form-stack"><h3>Yearly</h3><p><strong>₱10,000</strong> per year · Save ₱2,000</p><form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.upgrade') }}" data-app-form>@csrf<input type="hidden" name="plan" value="yearly"><button class="secondary" data-app-submit @disabled($subscription?->requested_plan==='yearly')>Choose yearly</button></form></section>
    @endif
    @if(\App\Services\VenueContext::onApp() && \App\Services\PlatformRewards::visible($venue,$subscription))
        <a class="button secondary" href="{{ \App\Services\VenueContext::url('settings.rewards') }}"><x-workspace-icon name="gift"/> View subscription rewards</a>
    @endif
    <a href="{{ route('product.legal.show',['document'=>'subscription-terms']) }}" target="_blank" rel="noopener">Subscription Terms</a>
</section>
</div>
@endsection
