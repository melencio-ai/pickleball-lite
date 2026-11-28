@extends('help.layout')
@section('content')
<section class="help-hero">
    <p class="help-eyebrow">PICKLE LITE SUPPORT</p>
    <h1>How can we help you?</h1>
    <p>Learn how to set up and run your venue, manage bookings and get the most out of Pickle Lite.</p>
    @include('help.search-form')
</section>
<section class="help-section" aria-labelledby="browse-heading"><div class="help-section-heading"><div><h2 id="browse-heading">Browse by topic</h2><p>Step-by-step guides for every part of Pickle Lite.</p></div><span>{{ count($searchIndex) }} guides</span></div>
    <div class="help-categories">
    @foreach($categories as $key=>$group)
        <a class="help-category" href="/category/{{ $key }}"><span class="help-icon"><x-booking-icon :name="$group['icon']"/></span><div><h3>{{ $group['title'] }}</h3><p>{{ $group['description'] }}</p><small>{{ count($group['articles']) }} articles</small></div><x-booking-icon name="chevron-right"/></a>
    @endforeach
    </div>
</section>
<section class="help-section" aria-labelledby="popular-heading"><h2 id="popular-heading">Start here</h2><div class="help-list">
    @foreach(array_slice(\App\Services\HelpLibrary::articles('getting-started'),0,6) as $item)
        @include('help.article-row',['item'=>$item])
    @endforeach
</div></section>
@include('help.support')
@endsection
