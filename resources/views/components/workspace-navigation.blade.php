@props(['navigation','venue'=>null,'logoUrl'=>null])
<div class="workspace-navigation-top">
    <div class="workspace-more-heading"><strong>More</strong><button type="button" class="quiet" data-mobile-more-close aria-label="Close menu"><x-booking-icon name="x"/></button></div>
    <a class="workspace-switch" href="{{ route('app.venues') }}" data-workspace-switch-open title="Switch workspace"
        aria-label="Switch workspace: {{ $navigation['label'] }}" aria-haspopup="dialog" aria-controls="workspace-switcher" aria-expanded="false">
        @if($venue && $logoUrl)
            <img class="workspace-switch-logo" src="{{ $logoUrl }}" alt="" width="28" height="28">
        @else
            <span class="workspace-switch-mark"><x-workspace-icon :name="$navigation['context']==='platform'?'shield':($venue?'building':'grid')"/></span>
        @endif
        <span class="workspace-switch-copy"><strong>{{ $navigation['label'] }}</strong><small>{{ $navigation['description'] }}</small></span>
        <span class="workspace-switch-chevron"><x-workspace-icon name="chevrons"/></span>
    </a>
</div>
<div class="workspace-navigation-scroll" data-nav-context="{{ $navigation['context'] }}">
    @foreach($navigation['groups'] as $caption=>$items)
        <div class="workspace-nav-group {{ collect($items)->every(fn($item)=>in_array($item['id'],array_column($navigation['mobile'],'id'),true)) ? 'workspace-mobile-primary-group' : '' }}" role="group" aria-label="{{ $caption }}">
            <span class="workspace-nav-caption">{{ $caption }}</span>
            @foreach($items as $item)
                <x-workspace-nav-item :item="$item"/>
            @endforeach
        </div>
    @endforeach
</div>
<div class="workspace-navigation-footer">
    <a class="workspace-nav-link workspace-notifications" href="{{ route('app.notifications.index') }}" title="Notifications" aria-label="Notifications" aria-haspopup="dialog" aria-controls="notification-drawer" aria-expanded="false" data-notification-open @if(request()->routeIs('app.notifications.index')) aria-current="page" @endif><x-workspace-icon name="bell"/><span>Notifications</span></a>
    <div class="workspace-logout"><button type="button" class="quiet workspace-nav-link" title="Sign out" aria-label="Sign out" aria-haspopup="dialog" aria-controls="sign-out-confirmation" data-sign-out-open><x-workspace-icon name="logout"/><span>Sign out</span></button></div>
</div>
