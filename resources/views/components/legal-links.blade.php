@props(['venue'=>null,'subscription'=>false])
<span class="legal-links" aria-label="Legal information">
    <a href="{{ route('product.legal.show',['document'=>'terms']) }}">Terms of Use</a>
    <a href="{{ route('product.legal.show',['document'=>'privacy']) }}">Privacy Policy</a>
    <a href="{{ route('product.legal.show',['document'=>'cookies']) }}">Cookie Policy</a>
    @if($subscription)<a href="{{ route('product.legal.show',['document'=>'subscription-terms']) }}">Subscription Terms</a>@endif
    @if($venue && $venue->acceptsPublicBookings())<a href="{{ \App\Services\VenueContext::url('policies',venue:$venue) }}">Venue policies</a>@endif
</span>
