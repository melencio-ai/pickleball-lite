@props(['variant'=>'primary'])
<span {{ $attributes->class(['platform-logo-lockup']) }}><img data-platform-brand="{{ $variant==='reverse'?'reverse':'primary' }}" class="platform-wordmark" src="/brand/pickle-lite-horizontal-{{ $variant==='reverse'?'reverse':'primary' }}.svg?v=court1" width="487" height="160" alt=""><span class="platform-brand-label">Pickle Lite</span></span>
