@props(['url'])
@php($variants=\App\Services\MessengerLinks::variants($url))
<a {{ $attributes }} href="{{ \App\Services\MessengerLinks::mobileRequest() ? $variants['mobile'] : $variants['desktop'] }}"
    @if($variants['desktop']!==$variants['mobile']) data-messenger-desktop="{{ $variants['desktop'] }}" data-messenger-mobile="{{ $variants['mobile'] }}" @endif
    target="_blank" rel="noopener noreferrer">{{ $slot }}</a>
