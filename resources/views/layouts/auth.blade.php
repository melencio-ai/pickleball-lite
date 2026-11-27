<!doctype html>
<html lang="en" style="{{ \App\Services\VenueTheme::style(null) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') · Pickle Lite</title>
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/auth.css?v=1">
    <script src="/auth.js?v=1" defer></script>
<link rel="stylesheet" href="/legal.css?v=1">
<link rel="stylesheet" href="/workspace.css?v=2">
<script src="/workspace.js?v=3" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <aside class="auth-aside" aria-label="About Pickle Lite">
            <a class="auth-brand" href="{{ route('product.home') }}"><img src="/favicon.svg" width="36" height="36" alt=""><span>Pickle Lite</span></a>
            <div class="auth-intro">
                <p class="auth-eyebrow">BUILT FOR PICKLEBALL VENUES</p>
                <h2>Your venue.<br>Simplified.</h2>
                <p>Everything you need to manage your pickleball venue.</p>
                <ul class="auth-benefits">
                    @foreach([['calendar','Manage court bookings'],['users','Organize Open Play'],['qr','Verify player payments'],['globe','Manage your venue website']] as [$icon,$label])
                        <li><span class="auth-benefit-icon"><x-auth-icon :name="$icon" /></span>{{ $label }}</li>
                    @endforeach
                </ul>
            </div>
            <p class="auth-aside-footer">More games. Less admin work.</p>
        </aside>
        <section class="auth-main" aria-label="@yield('title')">
            <a class="auth-brand auth-mobile-brand" href="{{ route('product.home') }}"><img src="/favicon.svg" width="36" height="36" alt=""><span>Pickle Lite</span></a>
            <div class="auth-content">
                @if(session('status'))
                    <div class="auth-notice" role="status" data-toast-success>{{ session('status') }}</div>
                @endif
                @if($errors->any())
                    <div class="auth-error" role="alert">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </div>
            <footer class="auth-footer">
                <span>&copy; {{ now()->year }} Pickle Lite</span>
                <a href="{{ route('product.home') }}">About Pickle Lite</a>
            </footer>
            <div class="legal-footer"><x-legal-links /></div>
        </section>
    </main>
@auth
    <x-sign-out-dialog />
@endauth
<div class="app-toast-region" data-toast-region aria-live="polite" aria-atomic="false"></div>
</body>
</html>
