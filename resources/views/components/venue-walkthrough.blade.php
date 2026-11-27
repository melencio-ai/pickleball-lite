@props(['video'=>null,'auto'=>false])
<dialog id="venue-walkthrough" class="walkthrough-dialog" aria-labelledby="walkthrough-title" aria-describedby="walkthrough-description" data-walkthrough-dialog @if($auto) data-walkthrough-auto @endif>
    <div class="walkthrough-heading">
        <div>
            <p class="walkthrough-eyebrow">{{ $auto ? 'Your venue is live!' : 'Pickle Lite guide' }}</p>
            <h2 id="walkthrough-title">Your 5-minute walkthrough</h2>
            <p id="walkthrough-description">Get familiar with your venue workspace and start accepting bookings.</p>
        </div>
        <button type="button" class="secondary walkthrough-close" aria-label="Close walkthrough" data-walkthrough-close><x-booking-icon name="x"/></button>
    </div>
    @if($video)
        <div class="walkthrough-player" data-walkthrough-player>
            <button type="button" class="walkthrough-play" data-walkthrough-play data-video-src="{{ $video['embed'] }}">
                <span class="walkthrough-play-icon"><x-workspace-icon name="play"/></span>
                <span>Play walkthrough</span>
                <small>5 minutes</small>
            </button>
        </div>
        <p class="walkthrough-note">Playing the video connects to the video provider. <a href="{{ $video['watch'] }}" target="_blank" rel="noopener noreferrer">Open video in a new tab</a></p>
    @else
        <div class="walkthrough-unavailable">
            <span class="walkthrough-play-icon"><x-workspace-icon name="play"/></span>
            <strong>Walkthrough video coming soon</strong>
            <p>In the meantime, use your calendar to manage bookings, Open Play to create sessions, and Settings to update your venue.</p>
        </div>
    @endif
    <div class="walkthrough-actions">
        <button type="button" data-walkthrough-close autofocus>Go to calendar</button>
    </div>
</dialog>
@if($auto || $video)
<noscript><p class="notice">Your venue guide: @if($video)<a href="{{ $video['watch'] }}" target="_blank" rel="noopener noreferrer">Watch the walkthrough</a>@else The walkthrough video is coming soon.@endif</p></noscript>
@endif
