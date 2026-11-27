<!doctype html>
@php
    $workspace = request()->attributes->get('venue_context');
    $appShell = \App\Services\VenueContext::onApp() && auth()->check();
    $publicVenue = \App\Services\VenueContext::onBooking() ? $workspace : null;
    $staffVenue = auth()->check() && \App\Services\VenueContext::role() ? $workspace : null;
    $brandVenue = $publicVenue ?? $staffVenue;
    $logoVersion = $brandVenue
        ? (isset($media) ? $media->get('logo')?->version : $brandVenue->media()->where('kind','logo')->value('version'))
        : null;
    $logoUrl = $logoVersion ? \App\Services\VenueContext::url('book.image',['kind'=>'logo','version'=>$logoVersion],venue:$brandVenue) : null;
@endphp
<html lang="en" style="{{ \App\Services\VenueTheme::style(($venue ?? request()->attributes->get('venue_context'))?->brand_color) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ $brandVenue?->name ?? 'Pickleball Lite' }}</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=tenant1">
    @if($logoUrl)
        <link rel="icon" href="{{ $logoUrl }}">
    @else
        <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @endif
    @if($publicVenue)
        <link rel="stylesheet" href="{{ asset('landing.css') }}?v=theme1">
    @endif
<link rel="stylesheet" href="/legal.css?v=1">
<link rel="stylesheet" href="/workspace.css?v=7">
<script src="/workspace.js?v=7" defer></script>
</head>
@php
    $subscriptionStatus = $appShell && $workspace ? \App\Services\TrialAccess::subscription($workspace) : null;
    $rewardsState = $appShell && $workspace && \App\Services\VenueContext::role()==='owner' &&
        \App\Services\PlatformRewards::visible($workspace,$subscriptionStatus)
        ? \App\Services\PlatformRewards::summary($workspace,$subscriptionStatus) : null;
    $navigation = $appShell ? \App\Services\WorkspaceNavigation::build(auth()->user(),$staffVenue,$rewardsState) : null;
@endphp
<body @if($appShell) class="app-workspace" data-workspace-user="{{ auth()->id() }}" @endif>
    @if($publicVenue)
        <a class="site-skip" href="#main-content">Skip to content</a>
        <x-public-booking-header :venue="$publicVenue" :logo-version="$logoVersion"/>
    @else
    <header class="site-header">
        <div class="header-inner">
            @if($staffVenue)
                <a class="brand workspace-venue-brand" href="{{ \App\Services\VenueContext::url('staff.calendar') }}" title="{{ $staffVenue->name }}">
                    @if($logoUrl)<img class="workspace-venue-logo" src="{{ $logoUrl }}" alt="" width="32" height="32">
                    @else<span class="workspace-venue-mark"><x-workspace-icon name="building"/></span>@endif
                    <span class="workspace-brand-name">{{ $staffVenue->name }}</span>
                </a>
            @else
                <a class="brand" href="{{ \App\Services\VenueContext::onApp() ? route('app.home') : \App\Services\VenueContext::url('landing') }}"><span class="brand-mark" aria-hidden="true"></span> Pickle Lite</a>
            @endif
            @if($appShell)
            <div class="workspace-top-actions">
                <button type="button" class="workspace-toggle" aria-label="Toggle navigation" aria-controls="workspace-navigation" aria-expanded="true" data-workspace-toggle><x-workspace-icon name="menu"/></button>
                <a class="workspace-bell" href="{{ route('app.notifications.index') }}" aria-label="Notifications" aria-haspopup="dialog" aria-controls="notification-drawer" aria-expanded="false" data-notification-open data-notification-count-url="{{ route('app.notifications.count') }}"><x-workspace-icon name="bell"/><span class="unread-count" data-unread-count hidden>0</span></a>
                <span class="workspace-user" title="{{ auth()->user()->name }}"><x-workspace-icon name="user"/></span>
            </div>
            @endif
            <nav aria-label="Main navigation" @if($appShell) id="workspace-navigation" class="workspace-navigation" @endif>
                @if(\App\Services\VenueContext::onApp())
                    @auth
                        <x-workspace-navigation :navigation="$navigation" :venue="$staffVenue" :logo-url="$logoUrl"/>
                    @else
                        <a href="{{ route('product.home') }}">About Pickle Lite</a>
                    @endauth
                @elseif(\App\Services\VenueContext::onBooking())
                    <a href="{{ \App\Services\VenueContext::url('landing') }}">Venue website</a>
                    <a href="{{ \App\Services\VenueContext::url('book') }}">Book now</a>
                    <a href="{{ \App\Services\VenueContext::url('open-play') }}">Open Play</a>
                    <a href="{{ route('app.login') }}">Staff sign in</a>
                @else
                    @auth
                        <a href="{{ \App\Services\VenueContext::url('landing') }}">Website</a>
                        <a href="{{ \App\Services\VenueContext::url('book') }}">Public booking</a>
                        <a href="{{ route('staff.calendar') }}">Calendar</a>
                        <a href="{{ route('staff.open-play') }}">Open Play</a>
                        @can('manage-venue')
                            <a href="{{ route('settings.website') }}">Website settings</a>
                            <a href="{{ route('settings') }}">Settings</a>
                        @endcan
                        <button type="button" class="quiet" aria-haspopup="dialog" aria-controls="sign-out-confirmation" data-sign-out-open>Sign out</button>
                    @else
                        <a href="{{ \App\Services\VenueContext::url('landing') }}">Venue website</a>
                        <a href="{{ \App\Services\VenueContext::url('book') }}">Book now</a>
                        <a href="{{ \App\Services\VenueContext::url('open-play') }}">Open Play</a>
                        <a href="{{ route('login') }}">Staff sign in</a>
                    @endauth
                @endif
            </nav>
        </div>
        @if(!$appShell && \App\Services\VenueContext::onApp() && $workspace && auth()->check())
            <div class="workspace-strip header-inner">
                <strong>{{ $workspace->name }}</strong>
                <span class="muted">{{ ucfirst(\App\Services\VenueContext::role() ?? '') }}</span>
                <a href="{{ route('app.venues') }}">Switch venue</a>
            </div>
        @endif
    </header>
    @endif
    @if($appShell)<button type="button" class="workspace-backdrop" data-workspace-close aria-label="Close navigation" hidden></button>@endif
    <main id="main-content" class="page @yield('page-class')">
        @if($workspace && $appShell && $workspace->self_service)
            @if($subscriptionStatus?->status==='trial' && \App\Services\TrialAccess::allowed($workspace))
                <div class="workspace-trial"><x-workspace-icon name="clock"/><span>{{ max(1,(int)ceil(now()->diffInSeconds(\Carbon\CarbonImmutable::parse($subscriptionStatus->trial_ends_at))/86400)) }} days left in your trial</span>@can('manage-venue')<a href="{{ \App\Services\VenueContext::url('settings.subscription') }}">{{ \App\Services\TrialAccess::canExtend($subscriptionStatus) ? 'Extend trial' : 'View subscription' }}</a>@endcan</div>
            @elseif(!\App\Services\TrialAccess::allowed($workspace))
                <div class="workspace-trial expired"><x-workspace-icon name="clock"/><span>Subscription inactive. Existing bookings remain available.</span>@can('manage-venue')<a href="{{ \App\Services\VenueContext::url('settings.subscription') }}">View subscription</a>@endcan</div>
            @endif
        @endif
        @if($rewardsState && !\App\Services\VenueContext::is('settings.rewards'))
            <x-rewards-widget :state="$rewardsState"/>
        @endif
        @if(session('status'))
            <div class="notice success" role="status" data-toast-success>{{ session('status') }}</div>
        @endif
        @if($errors->any() && !\App\Services\VenueContext::is('book'))
            <div class="notice error" role="alert">
                <ul>
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="legal-footer">
        @if($publicVenue)
            <p style="text-align:center;margin:0 0 12px;font-size:12px">{{ $publicVenue->name }} · Powered by Pickle Lite · <a href="{{ route('app.login') }}">Staff sign in</a></p>
        @endif
        <x-legal-links :venue="$publicVenue ?? $venue ?? null" />
    </footer>
@if($appShell)
    <x-workspace-switcher :navigation="$navigation" :venue="$staffVenue"/>
    <x-notification-drawer />
@endif
@if($rewardsState)<x-rewards-drawer :state="$rewardsState" :venue="$workspace"/>@endif
@if(auth()->check() && !\App\Services\VenueContext::onBooking())
    <x-sign-out-dialog />
@endif
<div class="app-toast-region" data-toast-region aria-live="polite" aria-atomic="false"></div>
</body>
</html>
