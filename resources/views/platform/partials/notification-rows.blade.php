@forelse($records as $record)
    <article class="notification-row {{ $record->read_at?'':'unread' }}" data-notification-record="{{ $record->id }}">
        <span class="notification-icon"><x-booking-icon name="{{ match($record->category){'booking'=>'calendar-days','payment'=>'qr-code','open_play'=>'users',default=>'circle-alert'} }}"/></span>
        <div class="notification-content"><strong>{{ $record->title }}</strong><p>{{ $record->message }}</p><small>{{ $record->venue_name }} · {{ \Carbon\CarbonImmutable::parse($record->created_at)->diffForHumans() }}</small>
        @php($destination=match($record->destination){'subscription'=>'app.settings.subscription','open-play'=>'app.staff.open-play','settings'=>'app.settings',default=>'app.staff.calendar'})
        <a href="{{ route($destination,['venueSlug'=>$record->slug]) }}">View</a></div>
        @unless($record->read_at)<form method="post" action="{{ route('app.notifications.read',$record->id) }}" data-notification-action>@csrf<button class="secondary small" aria-label="Mark notification as read">Mark read</button></form>@endunless
    </article>
@empty
    <p class="muted">You're all caught up. Notifications will appear here.</p>
@endforelse
