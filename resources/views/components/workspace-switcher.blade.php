@props(['navigation','venue'=>null])
<dialog id="workspace-switcher" class="workspace-switcher-dialog" aria-labelledby="workspace-switcher-title" data-workspace-switcher>
    <header class="workspace-switcher-header"><div><h2 id="workspace-switcher-title">Switch workspace</h2><p class="muted">Choose where you want to work.</p></div><button type="button" class="secondary workspace-switcher-close" aria-label="Close workspace switcher" data-workspace-switch-close autofocus><x-booking-icon name="x"/></button></header>
    <div class="workspace-switcher-list">
        <p class="workspace-nav-caption">Your venues</p>
        @forelse($navigation['venues'] as $record)
            <a class="workspace-choice" href="{{ route('app.staff.calendar',['venueSlug'=>$record->slug]) }}" data-workspace-choice="{{ $record->slug }}" @if($venue?->id===$record->id) aria-current="true" @endif>
                <span class="workspace-choice-icon"><x-workspace-icon name="building"/></span>
                <span class="workspace-choice-copy"><strong>{{ $record->name }}</strong><small>{{ ucfirst($record->pivot->role) }} · {{ ucfirst($record->status) }}</small></span>
                @if($venue?->id===$record->id)<x-workspace-icon name="check"/>@else<x-booking-icon name="chevron-right"/>@endif
            </a>
        @empty
            <p class="workspace-switcher-empty muted">No assigned venues yet.</p>
        @endforelse
        <a class="workspace-choice" href="{{ route('app.venues') }}" data-workspace-choice="all" @if($navigation['context']==='account' && request()->routeIs('app.venues')) aria-current="page" @endif><span class="workspace-choice-icon"><x-workspace-icon name="grid"/></span><span class="workspace-choice-copy"><strong>My venues</strong><small>{{ $navigation['more']?'View all assigned venues':'View all venues' }}</small></span><x-booking-icon name="chevron-right"/></a>
        @if($navigation['admin'])
            <p class="workspace-nav-caption">Platform access</p>
            <a class="workspace-choice" href="{{ route('app.admin.index') }}" data-workspace-choice="platform" @if($navigation['context']==='platform') aria-current="true" @endif>
                <span class="workspace-choice-icon"><x-workspace-icon name="shield"/></span><span class="workspace-choice-copy"><strong>Super Admin</strong><small>Platform administration</small></span>@if($navigation['context']==='platform')<x-workspace-icon name="check"/>@else<x-booking-icon name="chevron-right"/>@endif
            </a>
        @endif
    </div>
</dialog>
