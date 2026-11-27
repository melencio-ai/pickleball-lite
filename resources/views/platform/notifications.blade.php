@extends('layout')
@section('title','Notifications')
@section('content')
<header class="section-heading page-heading"><div><h1>Notifications</h1><p class="muted">{{ $unread }} unread · Your authorized venues</p></div><form method="post" action="{{ route('app.notifications.read-all') }}">@csrf<button class="secondary">Mark all as read</button></form></header>
<section class="panel notification-list">
@forelse($records as $record)
    <article class="notification-row {{ $record->read_at?'':'unread' }}">
        <span class="notification-icon"><x-booking-icon name="{{ match($record->category){'booking'=>'calendar-days','payment'=>'qr-code','open_play'=>'users',default=>'circle-alert'} }}"/></span>
        <div class="notification-content"><strong>{{ $record->title }}</strong><p>{{ $record->message }}</p><small>{{ $record->venue_name }} · {{ \Carbon\CarbonImmutable::parse($record->created_at)->diffForHumans() }}</small>
        @php($destination=match($record->destination){'subscription'=>'app.settings.subscription','open-play'=>'app.staff.open-play','settings'=>'app.settings',default=>'app.staff.calendar'})
        <a href="{{ route($destination,['venueSlug'=>$record->slug]) }}">View</a></div>
        @unless($record->read_at)<form method="post" action="{{ route('app.notifications.read',$record->id) }}">@csrf<button class="secondary small" aria-label="Mark notification as read">Mark read</button></form>@endunless
    </article>
@empty
    <p class="muted">You're all caught up. Notifications will appear here.</p>
@endforelse
{{ $records->links() }}
</section>
<section class="panel legal-section"><h2>Notification preferences</h2><p class="muted">Choose which optional in-app notifications to receive. Essential trial and subscription notices stay enabled.</p>
<form method="post" action="{{ route('app.notifications.preferences') }}" class="form-stack">@csrf @method('PATCH')
@foreach(['booking'=>'Court bookings','payment'=>'Payment activity','open_play'=>'Open Play','account'=>'Account activity'] as $category=>$label)
<label class="checkbox"><input type="checkbox" name="enabled[]" value="{{ $category }}" @checked($preferences[$category] ?? true)> {{ $label }}</label>
@endforeach
<button>Save preferences</button></form></section>
@endsection
