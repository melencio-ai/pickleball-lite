@props(['state'])
<a class="rewards-widget" href="{{ \App\Services\VenueContext::url('settings.rewards') }}" aria-haspopup="dialog" aria-controls="rewards-drawer" data-rewards-open>
    <span class="rewards-flame"><x-workspace-icon name="flame"/></span>
    <span class="rewards-widget-main"><strong>{{ $state['streak'] }}-day streak</strong><small>{{ $state['today_done'] ? 'Today completed' : ($state['eligible'] ? 'Use your workspace today' : 'Streak earning paused') }}</small></span>
    @if($state['next'])<span class="rewards-widget-progress"><span><strong>{{ min($state['streak'],$state['next']) }}/{{ $state['next'] }}</strong> days</span><progress value="{{ min($state['streak'],$state['next']) }}" max="{{ $state['next'] }}"></progress></span>@endif
    <span class="rewards-widget-end"><x-workspace-icon name="gift"/><span>{{ $state['available'] ? $state['available'].' free month(s)' : 'Rewards' }}</span><x-booking-icon name="chevron-right"/></span>
</a>
