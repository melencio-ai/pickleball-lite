@extends('layout')
@section('title','Notifications')
@section('content')
<header class="section-heading page-heading"><div><h1>Notifications</h1><p class="muted">{{ $unread }} unread · Your authorized venues</p></div><form method="post" action="{{ route('app.notifications.read-all') }}">@csrf<button class="secondary">Mark all as read</button></form></header>
<section class="panel notification-list">
@include('platform.partials.notification-rows')
{{ $records->links() }}
</section>
<section class="panel legal-section"><h2>Notification preferences</h2><p class="muted">Choose which optional in-app notifications to receive. Essential trial and subscription notices stay enabled.</p>
<form method="post" action="{{ route('app.notifications.preferences') }}" class="form-stack">@csrf @method('PATCH')
@foreach(['booking'=>'Court bookings','payment'=>'Payment activity','open_play'=>'Open Play','account'=>'Account activity'] as $category=>$label)
<label class="checkbox"><input type="checkbox" name="enabled[]" value="{{ $category }}" @checked($preferences[$category] ?? true)> {{ $label }}</label>
@endforeach
<button>Save preferences</button></form></section>
@endsection
