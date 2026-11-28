<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>{{ $title }} · Pickle Lite Help Center</title>
    <meta name="description" content="{{ $description }}">
    <link rel="canonical" href="{{ $canonical }}">
    <x-platform-favicon/>
    <link rel="stylesheet" href="/help.css?v=1">
    <script src="/help.js?v=1" defer></script>
    <script src="/messenger.js?v=1" defer></script>
<link rel="stylesheet" href="/brand.css?v=court1">
</head>
<body class="help-site">
<a class="help-skip" href="#main-content">Skip to content</a>
<header class="help-header"><div class="help-container help-header-inner">
    <a class="help-brand" href="/"><span class="help-brand-copy"><x-platform-logo/><small>Help Center</small></span></a>
    <nav aria-label="Help Center"><a href="/">All guides</a><a href="https://{{ config('pickle.product_host') }}">Website</a><a class="help-button help-outline" href="https://{{ config('pickle.app_host') }}/login">Sign in <x-booking-icon name="chevron-right"/></a></nav>
</div></header>
<main id="main-content" class="help-container">
    @yield('content')
</main>
<footer class="help-footer"><div class="help-container help-footer-inner"><span>Pickle Lite Help Center</span><div><a href="https://{{ config('pickle.product_host') }}/terms">Terms</a><a href="https://{{ config('pickle.product_host') }}/privacy">Privacy</a><x-messenger-link :url="\App\Services\MessengerLinks::PLATFORM">Contact support</x-messenger-link></div></div></footer>
<script type="application/json" id="help-search-index">@json($searchIndex, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
</body>
</html>
