@props(['item'])
<a class="workspace-nav-link" href="{{ $item['href'] }}" data-nav-item="{{ $item['id'] }}" title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}"
    @if($item['active']) aria-current="page" @endif
    @if($item['external']) target="_blank" rel="noopener noreferrer" @endif
    @if($item['drawer']==='rewards') data-rewards-open aria-haspopup="dialog" aria-controls="rewards-drawer" @endif
>
    <x-workspace-icon :name="$item['icon']"/>
    <span class="workspace-nav-label">{{ $item['label'] }}</span>
    @if($item['meta']!==null)<span class="workspace-nav-meta" aria-hidden="true">{{ $item['meta'] }}</span>@endif
    @if($item['external'])<span class="workspace-nav-external"><x-workspace-icon name="external"/></span>@endif
</a>
