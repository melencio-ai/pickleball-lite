<!doctype html>
<html lang="en" class="venue-site-root">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pickle Lite | Your pickleball venue, online</title>
    <meta name="description" content="A branded website, online court bookings, Open Play, QR payments and a staff calendar. ₱1,000 per month, with free setup.">
    <link rel="canonical" href="{{ route('product.home') }}">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="stylesheet" href="{{ asset('app.css') }}?v=tenant1">
    <link rel="stylesheet" href="{{ asset('landing.css') }}?v=theme1">
<link rel="stylesheet" href="/legal.css?v=1">
<link rel="stylesheet" href="/workspace.css?v=1">
<script src="/workspace.js?v=1" defer></script>
</head>
<body class="venue-site">
    @php($selfSignup=\App\Services\PlatformEmail::registrationEnabled() && \App\Services\LegalPolicies::current()->count()===4)
    @php($signupUrl=$selfSignup?route('app.register'):'#get-started')
    <a class="site-skip" href="#main-content">Skip to content</a>
    <header class="venue-site-header"><div class="site-container site-navigation">
        <a class="site-logo" href="{{ route('product.home') }}"><img src="{{ asset('favicon.svg') }}" alt=""><strong>Pickle Lite</strong></a>
        <nav aria-label="Product navigation"><a href="#features">Features</a><a href="#pricing">Pricing</a><a href="{{ $signupUrl }}">{{ $selfSignup?'Start free trial':'Get started' }}</a><a href="{{ route('app.login') }}">Sign in</a></nav>
        <a class="site-button site-primary" href="{{ $signupUrl }}">{{ $selfSignup?'Start free trial':'Get started' }}</a>
    </div></header>
    <main id="main-content">
        <section class="site-container site-hero no-cover"><div class="site-hero-copy">
            <p class="site-eyebrow">Built for pickleball venues</p>
            <h1>More games. Less admin work.</h1>
            <p class="site-lead">Give your players a place to book courts, join Open Play and pay online. Manage your venue in one simple app.</p>
            <div class="site-actions"><a class="site-button site-primary" href="{{ $signupUrl }}">{{ $selfSignup?'Start free trial':'Get Pickle Lite' }} <x-booking-icon name="chevron-right" /></a><a class="site-button site-outline" href="{{ route('product.demo') }}">Ask for a demo</a></div>
            <p class="site-footnote">@if($selfSignup)30-day free trial · No credit card required · @endif Free setup · ₱1,000 per month</p>
        </div></section>
        <section class="site-container site-section" id="features">
            <div class="site-section-heading"><p class="site-eyebrow">Everything you need to get started</p><h2>Make it easy to book your courts</h2><p>Give your staff fewer messages to answer and your players a faster way to find a game.</p></div>
            <div class="site-option-grid">
                @foreach([
                    ['layout-grid','Online court booking','Players choose a date, time, duration and available court.'],
                    ['users','Open Play','Set up sessions and player limits. Let players reserve a spot.'],
                    ['qr-code','QR payments','Generate the right payment amount and verify receipts in Messenger.'],
                    ['calendar-days','Staff calendar','See court bookings, payment holds and Open Play in one view.'],
                    ['map-pin','Your own venue website','Show your logo, hours, court rates, facilities and photos.'],
                    ['message-circle','Your own booking link','Share a link on your Facebook page or in Messenger.'],
                ] as [$icon,$heading,$description])
                    <article class="site-option"><span class="site-icon"><x-booking-icon :name="$icon" /></span><h3>{{ $heading }}</h3><p>{{ $description }}</p></article>
                @endforeach
            </div>
        </section>
        <section class="site-band" id="pricing"><div class="site-container site-section site-two-column">
            <div class="site-section-heading"><p class="site-eyebrow">Simple pricing</p><h2>One venue. One affordable plan.</h2><p>Start with a branded booking website and management tools for your courts.</p></div>
            <div class="site-data-panel"><span class="muted">Monthly maintenance</span><h2 class="site-price">₱1,000<span class="muted">/month</span></h2><div class="site-data-row"><strong>Account creation and setup</strong><strong>Free</strong></div><p>Includes the venue website, court booking, Open Play, QR payment workflow and staff calendar.</p><a class="site-button site-primary" href="{{ $signupUrl }}">{{ $selfSignup?'Start free trial':'Get started' }}</a></div>
        </div></section>
        <section class="site-container site-section site-two-column" id="get-started">
            <div class="site-section-heading"><p class="site-eyebrow">Let's get your venue online</p><h2>Tell us about your venue</h2><p>Send your details to ask about setup or a live demo.</p></div>
            <section class="site-data-panel">
                @if(session('status'))<p class="notice success" role="status" data-toast-success>{{ session('status') }}</p>@endif
                @if($errors->any())<div class="notice error" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
                <form class="form-stack" method="post" action="{{ route('product.enquiry') }}">@csrf
                    <label>Venue name<input name="venue_name" value="{{ old('venue_name') }}" maxlength="100" required></label>
                    <label>Your name<input name="name" value="{{ old('name') }}" maxlength="100" autocomplete="name" required></label>
                    <label>Email<input name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required></label>
                    <label>Phone<input name="phone" type="tel" value="{{ old('phone') }}" maxlength="32" autocomplete="tel"></label>
                    <label>Message<textarea name="message" maxlength="1000" rows="3">{{ old('message') }}</textarea></label>
                    <div class="visually-hidden" aria-hidden="true"><label>Leave this blank<input name="website" tabindex="-1" autocomplete="off"></label></div>
                    <p class="site-footnote">We use your details to respond to your enquiry. Read our <a href="{{ route('product.legal.show',['document'=>'privacy']) }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.</p><button class="button">Send enquiry</button>
                </form>
            </section>
        </section>
    </main>
    <footer class="site-footer"><div class="site-container"><span>Pickle Lite</span><a href="{{ route('app.login') }}">Venue staff sign in</a></div><div class="legal-footer"><x-legal-links :subscription="true"/></div></footer>
    <div class="site-mobile-cta"><a class="site-button site-primary" href="{{ $signupUrl }}">{{ $selfSignup?'Start free trial':'Get started' }}</a></div>
    <script src="{{ asset('landing-navigation.js') }}?v=2" defer></script>
<div class="app-toast-region" data-toast-region aria-live="polite" aria-atomic="false"></div>
</body>
</html>
