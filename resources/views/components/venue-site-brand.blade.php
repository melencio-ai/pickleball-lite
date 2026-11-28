@props(['venue','logoVersion'=>null])
<a class="site-logo" href="{{ \App\Services\VenueContext::url('landing',venue:$venue) }}">
    @if($logoVersion)
        <img src="{{ \App\Services\VenueContext::url('book.image',['kind'=>'logo','version'=>$logoVersion],venue:$venue) }}" alt="">
    @else
        <x-platform-mark/>
    @endif
    <strong>{{ $venue->name }}</strong>
</a>
