@extends('layout')
@section('title','Pickle Lite Rewards')
@section('content')
<header class="section-heading page-heading"><div><h1>Pickle Lite Rewards</h1><p class="muted">{{ $venue->name }} · Earn up to four free subscription months</p></div><a class="button secondary" href="{{ \App\Services\VenueContext::url('settings.subscription') }}">My subscription</a></header>
<div class="settings-grid">
    <section class="panel form-stack"><h2>Usage streak</h2><x-rewards-milestones :state="$state"/><p class="muted">One qualifying day for authorized venue operations. Trial days count after your venue is published. Background requests and notification polling do not count. Your streak time zone is {{ $state['zone'] }} and is fixed on the first active day. Tracking starts when the program is introduced; no historical credit. Miss a full day and the current streak resets, but earned credits and longest streak remain.</p></section>
    <section class="panel form-stack">
        <h2>Share your story. Get one month free.</h2>
        @if($testimonial)<p class="notice">Submission: {{ ucfirst($testimonial->status) }} @if($testimonial->review_note) · {{ $testimonial->review_note }} @endif</p>@endif
        @if($state['activeDays']<15)<p>Unlock after 15 active days. {{ 15-$state['activeDays'] }} more days to go.</p>
        @elseif(!$state['grants']->has('testimonial') && (!$testimonial || $testimonial->status==='rejected'))
            <p>Record a short, honest video about your venue and actual experience. Send it to Pickle Lite via Messenger, then submit a reference below. Feedback does not need to be positive. Do not include player or banking information.</p>
            <x-messenger-link class="button secondary" :url="\App\Services\MessengerLinks::PLATFORM">Send video via Messenger <x-booking-icon name="chevron-right"/></x-messenger-link>
            <form method="post" class="form-stack" action="{{ \App\Services\VenueContext::url('settings.rewards.submit') }}" data-app-form>@csrf
                <label>Video reference / Messenger sender and date<textarea name="submission_reference" maxlength="500" rows="3" required placeholder="Private video link or Messenger sender and date">{{ old('submission_reference') }}</textarea></label>
                <label class="checkbox"><input type="checkbox" name="submitted" value="1" required> I sent my genuine video testimonial for review and understand the reward terms.</label>
                <label class="checkbox"><input type="checkbox" name="marketing_consent" value="1"> Optional: I allow Pickle Lite to publish my video for marketing with disclosure that I received a free month.</label>
                <button data-app-submit>Submit for review</button>
            </form>
        @else<p>Each venue can earn the testimonial reward once. Our team verifies submissions before issuing credits.</p>@endif
        @if($testimonial?->marketing_consent)
            <form method="post" action="{{ \App\Services\VenueContext::url('settings.rewards.withdraw') }}">@csrf<button class="secondary small">Withdraw marketing permission</button></form>
        @endif
        <div class="rewards-optional-review"><strong>Optional Facebook review</strong><p class="muted">Share honest feedback on Facebook if you wish. This is not required to earn any reward.</p><a href="https://www.facebook.com/pickleliteapp/reviews" target="_blank" rel="noopener noreferrer">Leave a Facebook review <x-booking-icon name="chevron-right"/></a></div>
    </section>
</div>
<section class="panel tenant-panel">
    <header class="section-heading"><div><h2>Subscription credits</h2><p class="muted">{{ $state['available'] }} available month(s) · {{ $state['earned'] }} earned month(s)</p></div></header>
    <p class="muted">One-time credits are non-transferable and have no cash value. During a trial they stay available. Apply them to a valid subscription renewal, or start subscription access when your trial ends. Credits do not create cash payments.</p>
    <div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Reward</th><th>Months</th><th>Earned</th><th>Status</th><th>Action</th></tr></thead><tbody>
    @forelse($state['grants'] as $grant)
        <tr><td>{{ \App\Services\PlatformRewards::LABELS[$grant->milestone] }}</td><td>{{ $grant->months }}</td><td>{{ \Carbon\CarbonImmutable::parse($grant->earned_at)->setTimezone($venue->timezone)->format('M j, Y') }}</td><td>{{ $grant->revoked_at?'Revoked':($grant->applied_at?'Applied':'Available') }} @if($grant->new_renewal)<small>Renewal: {{ $grant->new_renewal }}</small>@endif @if($grant->revoke_reason)<small>{{ $grant->revoke_reason }}</small>@endif</td><td>
        @if(!$grant->applied_at && !$grant->revoked_at && $state['can_apply'])
            <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('settings.rewards.apply',['grant'=>$grant->id]) }}" data-app-form>@csrf<label class="checkbox"><input type="checkbox" name="confirmed" value="1" required> Confirm application</label><button class="small" data-app-submit>Apply {{ $grant->months }} month(s)</button></form>
        @elseif(!$grant->applied_at && !$grant->revoked_at)<span class="muted">Saved for eligible renewal</span>@else — @endif
        </td></tr>
    @empty<tr><td colspan="5">No credits earned yet. Keep using your workspace.</td></tr>@endforelse
    </tbody></table></div>
    <p class="muted">Only unused credits can be revoked for invalid awards, with a recorded reason. Contact Pickle Lite for corrections.</p>
</section>
@endsection
