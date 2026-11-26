<!doctype html>
<html lang="en" style="{{ \App\Services\VenueTheme::style(($venue ?? auth()->user()?->venue)?->brand_color) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · Pickleball Lite</title>
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=theme1">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
</head>
<body>
    <header class="site-header">
        <div class="header-inner">
            <a class="brand" href="{{ route('landing') }}"><span class="brand-mark" aria-hidden="true"></span> Pickleball Lite</a>
            <nav aria-label="Main navigation">
                @auth
                    <a href="{{ route('landing') }}">Website</a>
                    <a href="{{ route('book') }}" @if(request()->routeIs('book*')) aria-current="page" @endif>Public booking</a>
                    <a href="{{ route('staff.calendar') }}" @if(request()->routeIs('staff.calendar')) aria-current="page" @endif>Calendar</a>
                    <a href="{{ route('staff.open-play') }}" @if(request()->routeIs('staff.open-play*')) aria-current="page" @endif>Open Play</a>
                    @can('manage-venue')
                        <a href="{{ route('settings.website') }}" @if(request()->routeIs('settings.website*')) aria-current="page" @endif>Website settings</a>
                        <a href="{{ route('settings') }}" @if(request()->routeIs('settings*')) aria-current="page" @endif>Settings</a>
                    @endcan
                    <form method="post" action="{{ route('logout') }}">@csrf<button class="quiet">Sign out</button></form>
                @else
                    <a href="{{ route('landing') }}">Venue website</a>
                    <a href="{{ route('book') }}" @if(request()->routeIs('book*')) aria-current="page" @endif>Book now</a>
                    <a href="{{ route('open-play') }}" @if(request()->routeIs('open-play*')) aria-current="page" @endif>Open Play</a>
                    <a href="{{ route('login') }}">Staff sign in</a>
                @endauth
            </nav>
        </div>
    </header>
    <main class="page @yield('page-class')">
        @if(session('status'))
            <div class="notice success" role="status">{{ session('status') }}</div>
        @endif
        @if($errors->any() && !request()->routeIs('book'))
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
