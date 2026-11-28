@extends('help.layout')
@section('content')
<section class="help-hero help-search-page"><h1>Search guides</h1>@include('help.search-form')</section>
<section class="help-section help-results"><h2>@if($query==='') Find an article @else {{ count($results) }} results for “{{ $query }}” @endif</h2>
@if($query==='')<p>Enter a word or question above.</p>@elseif(!$results)<p>No matching guides. Try a shorter phrase, or <a href="/">browse all topics</a>.</p>@else<div class="help-list">@foreach($results as $item)@include('help.article-row',['item'=>$item])@endforeach</div>@endif
</section>@include('help.support')
@endsection
