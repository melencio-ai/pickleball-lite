<dialog id="notification-drawer" class="notification-drawer" aria-labelledby="notification-drawer-title" data-notification-drawer data-drawer-url="{{ route('app.notifications.drawer') }}">
    <header class="notification-drawer-header">
        <div><h2 id="notification-drawer-title">Notifications</h2><p class="muted" data-drawer-count>Your authorized venues</p></div>
        <button class="secondary notification-drawer-close" type="button" aria-label="Close notifications" data-drawer-close autofocus><x-booking-icon name="x"/></button>
    </header>
    <div class="notification-drawer-toolbar">
        <form method="post" action="{{ route('app.notifications.read-all') }}" data-notification-action>@csrf
            <button type="submit" class="secondary small" data-drawer-read-all disabled>Mark all as read</button>
        </form>
        <button class="secondary small notification-drawer-refresh" type="button" data-drawer-refresh aria-label="Refresh notifications"><x-booking-icon name="refresh-cw"/></button>
    </div>
    <p class="notification-drawer-status" data-drawer-status role="status" aria-live="polite" hidden></p>
    <section class="notification-drawer-content" data-drawer-content aria-label="Notification list" tabindex="-1"></section>
    <template data-drawer-loading>
        <div class="notification-loading" role="status"><span class="visually-hidden">Loading notifications</span>
            @for($i=0;$i<4;$i++)<div aria-hidden="true" class="notification-skeleton"><span></span><div><i></i><i></i></div></div>@endfor
        </div>
    </template>
    <footer class="notification-drawer-footer">
        <a href="{{ route('app.notifications.index') }}">View all notifications &amp; preferences <x-booking-icon name="chevron-right"/></a>
    </footer>
</dialog>
