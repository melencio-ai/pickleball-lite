@extends('help.layout')
@section('content')
<div class="help-topbar"><nav class="help-breadcrumb" aria-label="Breadcrumb"><a href="/">Help Center</a><span>/</span><span>{{ $group['title'] }}</span></nav>@include('help.search-form')</div>
<div class="help-workspace">@include('help.sidebar')<section class="help-reading"><div class="help-category-heading"><span class="help-icon"><x-booking-icon :name="$group['icon']"/></span><h1>{{ $group['title'] }}</h1><p>{{ $group['description'] }} · {{ count($articles) }} articles</p></div><div class="help-list">@foreach($articles as $item)@include('help.article-row',['item'=>$item])@endforeach</div>@include('help.support')</section></div>
@endsection
