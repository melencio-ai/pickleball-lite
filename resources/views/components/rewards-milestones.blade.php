@props(['state'])
<div class="rewards-stats">
    <div><span class="muted">Current streak</span><strong class="rewards-streak-number"><x-workspace-icon name="flame"/>{{ $state['streak'] }} days</strong></div>
    <div><span class="muted">Longest streak</span><strong>{{ $state['longest'] }} days</strong></div>
    <div><span class="muted">Available credit</span><strong>{{ $state['available'] }} month(s)</strong></div>
</div>
<p class="muted rewards-today">{{ $state['today_done'] ? 'Today is complete.' : ($state['eligible'] ? 'Use your calendar, Open Play or venue settings today to keep your streak.' : 'Earning is paused until your venue has an eligible subscription or trial.') }}</p>
<div class="rewards-week" aria-label="This week's activity">
    @foreach(range(0,6) as $i)
        @php($date=now($state['zone'])->startOfWeek()->addDays($i))
        <div><small>{{ $date->format('D') }}</small><span class="{{ in_array($date->toDateString(),$state['days'],true)?'done':'' }}" aria-label="{{ $date->format('M j') }} {{ in_array($date->toDateString(),$state['days'],true)?'completed':'not completed' }}"><x-workspace-icon name="flame"/></span></div>
    @endforeach
</div>
@foreach([100=>1,300=>2] as $target=>$months)
    @php($grant=$state['grants']->get('streak_'.$target))
    <div class="rewards-milestone">
        <div class="rewards-milestone-heading"><div><strong>{{ $target }}-day streak</strong><small>{{ $months }} free subscription {{ $months===1?'month':'months' }}</small></div><span class="status-chip">{{ $grant ? ($grant->revoked_at?'Revoked':($grant->applied_at?'Applied':'Earned')) : 'In progress' }}</span></div>
        <progress value="{{ $grant ? $target : min($state['streak'],$target) }}" max="{{ $target }}"></progress>
        <small class="muted">{{ $grant ? 'Milestone awarded once per venue.' : min($state['streak'],$target).' / '.$target.' consecutive days · '.max(0,$target-$state['streak']).' remaining' }}</small>
    </div>
@endforeach
<div class="rewards-milestone"><div class="rewards-milestone-heading"><div><strong>Share your story</strong><small>1 free month · genuine video testimonial</small></div><span class="status-chip">{{ $state['grants']->has('testimonial') ? 'Award recorded' : ($state['activeDays']>=15?'Unlocked':'Locked') }}</span></div><progress value="{{ min(15,$state['activeDays']) }}" max="15"></progress><small class="muted">{{ min(15,$state['activeDays']) }}/15 distinct active days. Facebook reviews are optional.</small></div>
