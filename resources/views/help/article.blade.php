@extends('help.layout')
@section('content')
<div class="help-topbar"><nav class="help-breadcrumb" aria-label="Breadcrumb"><a href="/">Help Center</a><span>/</span><a href="/category/{{ $category }}">{{ $article['categoryTitle'] }}</a><span>/</span><span>Article</span></nav>@include('help.search-form')</div>
<div class="help-workspace">@include('help.sidebar')<div class="help-reading">
<article class="help-article"><header><p class="help-eyebrow">{{ $article['categoryTitle'] }}</p><h1>{{ $article['title'] }}</h1><p class="help-description">{{ $article['description'] }}</p><div class="help-meta"><x-booking-icon name="clock"/> {{ $minutes }} min read <span>· Updated {{ $article['updated'] }}</span></div></header>
@if(count($toc))<nav class="help-toc" aria-label="On this page"><strong>On this page</strong>@foreach($toc as $entry)<a href="#{{ $entry['id'] }}">{{ $entry['title'] }}</a>@endforeach</nav>@endif
<div class="help-prose">{!! $html !!}</div>
<a class="help-button help-outline" href="https://{{ config('pickle.app_host') }}/login">Open Pickle Lite <x-booking-icon name="chevron-right"/></a>
</article>
<nav class="help-pager" aria-label="Previous and next guides">@if($previous)<a href="{{ $previous['path'] }}"><small>Previous</small><strong>{{ $previous['title'] }}</strong></a>@endif @if($next)<a href="{{ $next['path'] }}"><small>Next</small><strong>{{ $next['title'] }}</strong></a>@endif</nav>
@if(count($related))<section class="help-section"><h2>Related articles</h2><div class="help-list">@foreach($related as $item)@include('help.article-row',['item'=>$item])@endforeach</div></section>@endif
@include('help.support')
</div></div>
@endsection
