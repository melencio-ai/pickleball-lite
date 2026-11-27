@extends('layout')
@section('title','My subscription')
@section('content')
@php
$end=$subscription?->trial_ends_at ? \Carbon\CarbonImmutable::parse($subscription->trial_ends_at) : null;
$days=$end && $end->isFuture() ? (int)ceil(now()->diffInSeconds($end)/86400) : 0;
$status=$subscription?->status ?? 'onboarding';
if($status==='trial' && !$days) $status='trial_expired';
if($status==='active' && $subscription?->reward_managed && !\App\Services\TrialAccess::allowed($venue)) $status='overdue';
@endphp
<header class="section-heading page-heading"><div><h1>My subscription</h1><p class="muted">{{ $venue->name }}</p></div><span class="status-chip">{{ ucfirst(str_replace('_',' ',$status)) }}</span></header>
<div class="settings-grid">
<section class="panel form-stack">
    @if($status==='trial')
        <h2>{{ $days }} {{ $days===1?'day':'days' }} remaining</h2>
        <p>Trial ends {{ $end->setTimezone($venue->timezone)->format('M j, Y g:i A') }}. {{ 3-($subscription->trial_extensions_count ?? 0) }} extensions remaining.</p>
        @if($canExtend)<div class="notice"><strong>Need more time?</strong><p>Extend your free trial by 15 days. No charge.</p><form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.extend') }}" data-app-form>@csrf<input type="hidden" name="extension_count" value="{{ $subscription->trial_extensions_count }}"><button data-app-submit>Extend free trial</button></form></div>@endif
    @elseif($status==='trial_expired')
        <h2>Your free trial has ended</h2><p>New reservations are unavailable. Existing bookings, payments and account records remain accessible.</p>
    @elseif($status==='active')
        <h2>{{ $subscription->reward_managed ? 'Subscription (reward-managed)' : 'Paid subscription' }}</h2><p>Next renewal: {{ $subscription->renewal_date ?? 'Contact Pickle Lite' }}</p>
    @else
        <h2>{{ ucfirst(str_replace('_',' ',$status)) }}</h2><p>Contact Pickle Lite for assistance with your subscription.</p>
    @endif
    @if($end)<p class="muted">Original trial start: {{ \Carbon\CarbonImmutable::parse($subscription->trial_started_at)->setTimezone($venue->timezone)->format('M j, Y') }}</p>@endif
</section>
<section class="panel form-stack">
    <h2>Paid plan</h2>
    <p><strong>₱{{ number_format(($subscription->monthly_fee ?? 100000)/100) }}</strong> per month · Free setup</p>
    <p>Includes booking website, court reservations, Open Play and management tools. No automatic charges.</p>
    @if($status!=='active')
        @if($subscription?->upgrade_requested_at)<p class="notice success">Upgrade requested. We will contact you to arrange payment.</p>
        @else<form method="post" action="{{ \App\Services\VenueContext::url('settings.subscription.upgrade') }}" data-app-form>@csrf<button data-app-submit>Request paid upgrade</button></form>@endif
    @endif
    @if(\App\Services\VenueContext::onApp() && \App\Services\PlatformRewards::visible($venue,$subscription))
        <a class="button secondary" href="{{ \App\Services\VenueContext::url('settings.rewards') }}"><x-workspace-icon name="gift"/> View subscription rewards</a>
    @endif
    <a href="{{ route('product.legal.show',['document'=>'subscription-terms']) }}" target="_blank" rel="noopener">Subscription Terms</a>
</section>
</div>
@endsection
