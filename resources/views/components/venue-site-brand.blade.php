@props(['venue','logoVersion'=>null])
<a class="site-logo" href="{{ \App\Services\VenueContext::url('landing',venue:$venue) }}">
    @if($logoVersion)
        <img src="{{ \App\Services\VenueContext::url('book.image',['kind'=>'logo','version'=>$logoVersion],venue:$venue) }}" alt="">
    @else
        <span class="site-mark"><x-booking-icon name="layout-grid"/></span>
    @endif
    <strong>{{ $venue->name }}</strong>
</a>
