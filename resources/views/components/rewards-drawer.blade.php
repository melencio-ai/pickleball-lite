@props(['state','venue'])
<dialog id="rewards-drawer" class="notification-drawer rewards-drawer" aria-labelledby="rewards-drawer-title" data-rewards-drawer>
    <header class="notification-drawer-header"><div><h2 id="rewards-drawer-title">Pickle Lite Rewards</h2><p class="muted">{{ $venue->name }}</p></div><button class="secondary notification-drawer-close" type="button" aria-label="Close rewards" data-rewards-close autofocus><x-booking-icon name="x"/></button></header>
    <div class="notification-drawer-content rewards-drawer-content"><x-rewards-milestones :state="$state"/><p class="muted rewards-today">Streaks count one day per venue, in {{ $state['zone'] }}. Miss a full day and the current streak resets; earned rewards remain.</p></div>
    <footer class="notification-drawer-footer"><a href="{{ \App\Services\VenueContext::url('settings.rewards') }}">View rewards, submit testimonial or apply credits <x-booking-icon name="chevron-right"/></a></footer>
</dialog>
