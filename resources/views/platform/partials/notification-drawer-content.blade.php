<div data-drawer-unread="{{ $unread }}">
    @include('platform.partials.notification-rows')
    @if($records->hasPages())
        <nav class="notification-pagination" aria-label="Notification pages">
            @if($records->previousPageUrl())<a class="button secondary small" data-notification-page href="{{ $records->previousPageUrl() }}">Previous</a>@else<span></span>@endif
            <small class="muted">Page {{ $records->currentPage() }} of {{ $records->lastPage() }}</small>
            @if($records->hasMorePages())<a class="button secondary small" data-notification-page href="{{ $records->nextPageUrl() }}">Next</a>@else<span></span>@endif
        </nav>
    @endif
</div>
