<!doctype html>
<html lang="en" style="{{ \App\Services\VenueTheme::style(($venue ?? request()->attributes->get('venue_context'))?->brand_color) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Pickleball Lite</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=tenant1">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="stylesheet" href="/legal.css?v=1">
<link rel="stylesheet" href="/workspace.css?v=1">
<script src="/workspace.js?v=1" defer></script>
</head>
@php
    $workspace = request()->attributes->get('venue_context');
    $appShell = \App\Services\VenueContext::onApp() && auth()->check();
    $subscriptionStatus = $appShell && $workspace ? \App\Services\TrialAccess::subscription($workspace) : null;
@endphp
<body @if($appShell) class="app-workspace" data-workspace-user="{{ auth()->id() }}" @endif>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="{{ \App\Services\VenueContext::onApp() ? route('app.home') : \App\Services\VenueContext::url('landing') }}"><span class="brand-mark" aria-hidden="true"></span> Pickle Lite</a>
            @if($appShell)
            <div class="workspace-top-actions">
                <button type="button" class="workspace-toggle" aria-label="Toggle navigation" aria-controls="workspace-navigation" aria-expanded="true" data-workspace-toggle><x-workspace-icon name="menu"/></button>
                <a class="workspace-bell" href="{{ route('app.notifications.index') }}" aria-label="Notifications" data-notification-count-url="{{ route('app.notifications.count') }}"><x-workspace-icon name="bell"/><span class="unread-count" data-unread-count hidden>0</span></a>
                <span class="workspace-user" title="{{ auth()->user()->name }}"><x-workspace-icon name="user"/></span>
            </div>
            @endif
            <nav aria-label="Main navigation" @if($appShell) id="workspace-navigation" class="workspace-navigation" @endif>
                @if(\App\Services\VenueContext::onApp())
                    @auth
                        <a class="workspace-nav-link" href="{{ route('app.venues') }}" title="My venues"><x-workspace-icon name="grid"/><span>My venues</span></a>
                        @can('platform-admin')
                            <span class="workspace-nav-caption">Platform</span>
                            <a class="workspace-nav-link" href="{{ route('app.admin.index') }}" title="Platform admin"><x-workspace-icon name="shield"/><span>Platform admin</span></a>
                            <a class="workspace-nav-link" href="{{ route('app.admin.legal.index') }}" title="Legal policies"><x-workspace-icon name="file"/><span>Legal policies</span></a>
                        @endcan
                        @if($workspace)
                            <span class="workspace-nav-caption">Venue</span>
                            <a class="workspace-switch" href="{{ route('app.venues') }}" title="Switch venue"><x-workspace-icon name="building"/><span>{{ $workspace->name }}</span></a>
                            <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('landing') }}" target="_blank" rel="noopener" title="Venue website"><x-workspace-icon name="globe"/><span>Venue website</span></a>
                            <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('staff.calendar') }}" title="Calendar" @if(\App\Services\VenueContext::is('staff.calendar*')) aria-current="page" @endif><x-workspace-icon name="calendar"/><span>Calendar</span></a>
                            <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('staff.open-play') }}" title="Open Play" @if(\App\Services\VenueContext::is('staff.open-play*')) aria-current="page" @endif><x-workspace-icon name="users"/><span>Open Play</span></a>
                            @can('manage-website')
                                <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('settings.website') }}" title="Website settings"><x-workspace-icon name="globe"/><span>Website settings</span></a>
                            @endcan
                            @can('manage-venue')
                                @if($workspace->self_service && \Illuminate\Support\Facades\DB::table('venue_onboarding')->where('venue_id',$workspace->id)->whereNull('completed_at')->exists())
                                    <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('settings.onboarding') }}" title="Resume onboarding"><x-workspace-icon name="file"/><span>Resume onboarding</span></a>
                                @endif
                                <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('settings.subscription') }}" title="Subscription"><x-workspace-icon name="card"/><span>Subscription</span></a>
                                <a class="workspace-nav-link" href="{{ \App\Services\VenueContext::url('settings') }}" title="Settings"><x-workspace-icon name="settings"/><span>Settings</span></a>
                            @endcan
                        @endif
                        <a class="workspace-nav-link workspace-notifications" href="{{ route('app.notifications.index') }}" title="Notifications"><x-workspace-icon name="bell"/><span>Notifications</span></a>
                        <form method="post" class="workspace-logout" action="{{ route('app.logout') }}">@csrf<button class="quiet workspace-nav-link" title="Sign out"><x-workspace-icon name="logout"/><span>Sign out</span></button></form>
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
                        <form method="post" action="{{ route('logout') }}">@csrf<button class="quiet">Sign out</button></form>
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
    @if($appShell)<button type="button" class="workspace-backdrop" data-workspace-close aria-label="Close navigation" hidden></button>@endif
    <main class="page @yield('page-class')">
        @if($workspace && $appShell && $workspace->self_service)
            @if($subscriptionStatus?->status==='trial' && \App\Services\TrialAccess::allowed($workspace))
                <div class="workspace-trial"><x-workspace-icon name="clock"/><span>{{ max(1,(int)ceil(now()->diffInSeconds(\Carbon\CarbonImmutable::parse($subscriptionStatus->trial_ends_at))/86400)) }} days left in your trial</span>@can('manage-venue')<a href="{{ \App\Services\VenueContext::url('settings.subscription') }}">{{ \App\Services\TrialAccess::canExtend($subscriptionStatus) ? 'Extend trial' : 'View subscription' }}</a>@endcan</div>
            @elseif(!\App\Services\TrialAccess::allowed($workspace))
                <div class="workspace-trial expired"><x-workspace-icon name="clock"/><span>Subscription inactive. Existing bookings remain available.</span>@can('manage-venue')<a href="{{ \App\Services\VenueContext::url('settings.subscription') }}">View subscription</a>@endcan</div>
            @endif
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
    <footer class="legal-footer"><x-legal-links :venue="$venue ?? null" /></footer>
<div class="app-toast-region" data-toast-region aria-live="polite" aria-atomic="false"></div>
</body>
</html>
