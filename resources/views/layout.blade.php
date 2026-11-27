<!doctype html>
<html lang="en" style="{{ \App\Services\VenueTheme::style(($venue ?? request()->attributes->get('venue_context'))?->brand_color) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Pickleball Lite</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=tenant1">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
</head>
<body>
    @php($workspace = request()->attributes->get('venue_context'))
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="{{ \App\Services\VenueContext::onApp() ? route('app.home') : \App\Services\VenueContext::url('landing') }}"><span class="brand-mark" aria-hidden="true"></span> Pickle Lite</a>
            <nav aria-label="Main navigation">
                @if(\App\Services\VenueContext::onApp())
                    @auth
                        <a href="{{ route('app.venues') }}">My venues</a>
                        @can('platform-admin')<a href="{{ route('app.admin.index') }}">Platform admin</a>@endcan
                        @if($workspace)
                            <a href="{{ \App\Services\VenueContext::url('landing') }}" target="_blank" rel="noopener">Venue website</a>
                            <a href="{{ \App\Services\VenueContext::url('staff.calendar') }}" @if(\App\Services\VenueContext::is('staff.calendar*')) aria-current="page" @endif>Calendar</a>
                            <a href="{{ \App\Services\VenueContext::url('staff.open-play') }}" @if(\App\Services\VenueContext::is('staff.open-play*')) aria-current="page" @endif>Open Play</a>
                            @can('manage-venue')
                                <a href="{{ \App\Services\VenueContext::url('settings.website') }}">Website settings</a>
                                <a href="{{ \App\Services\VenueContext::url('settings') }}">Settings</a>
                            @endcan
                        @endif
                        <form method="post" action="{{ route('app.logout') }}">@csrf<button class="quiet">Sign out</button></form>
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
        @if(\App\Services\VenueContext::onApp() && $workspace && auth()->check())
            <div class="workspace-strip header-inner">
                <strong>{{ $workspace->name }}</strong>
                <span class="muted">{{ ucfirst(\App\Services\VenueContext::role() ?? '') }}</span>
                <a href="{{ route('app.venues') }}">Switch venue</a>
            </div>
        @endif
    </header>
    <main class="page @yield('page-class')">
        @if(session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
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
</body>
</html>
