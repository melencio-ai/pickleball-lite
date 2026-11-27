@props(['navigation'])
@php
    $ids=array_column($navigation['mobile'],'id');
    $isNotifications=request()->routeIs('app.notifications.index');
    $moreActive=!$isNotifications && collect($navigation['groups'])->flatten(1)->contains(fn($item)=>$item['active'] && !in_array($item['id'],$ids,true));
    $icons=['calendar'=>'calendar-days','platform-overview'=>'layout-dashboard'];
@endphp
<nav class="workspace-mobile-nav" aria-label="Mobile navigation" data-mobile-navigation data-mobile-context="{{ $navigation['context'] }}">
    @foreach($navigation['mobile'] as $item)
        <a class="workspace-tab" href="{{ $item['href'] }}" data-mobile-tab="{{ $item['id'] }}" @if($item['active']) aria-current="page" @endif>
            <span class="workspace-tab-icon"><x-workspace-icon :name="$icons[$item['id']] ?? $item['icon']"/></span>
            <span>{{ $item['label'] }}</span>
        </a>
    @endforeach
    <a class="workspace-tab workspace-mobile-alerts" href="{{ route('app.notifications.index') }}" data-mobile-tab="alerts" aria-label="Alerts" data-notification-label="Alerts" aria-haspopup="dialog" aria-controls="notification-drawer" aria-expanded="false" data-notification-open data-notification-count-url="{{ route('app.notifications.count') }}" @if($isNotifications) aria-current="page" @endif>
        <span class="workspace-tab-icon"><x-workspace-icon name="bell"/><span class="unread-count" data-unread-count hidden>0</span></span>
        <span>Alerts</span>
    </a>
    <button type="button" class="workspace-tab" data-mobile-more aria-controls="workspace-navigation" aria-expanded="false" @if($moreActive) aria-current="true" @endif>
        <span class="workspace-tab-icon"><x-workspace-icon name="more"/></span><span>More</span>
    </button>
</nav>
