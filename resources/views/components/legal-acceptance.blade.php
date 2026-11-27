@props(['legal','venue'=>null,'subscription'=>false])
<div class="legal-acceptance" style="grid-column:1/-1">
    @if($legal['terms'])
        <input type="hidden" name="legal_fingerprint" value="{{ $legal['fingerprint'] }}">
        <div class="legal-check">
            <input id="legal-accepted" type="checkbox" name="legal_accepted" value="1" required>
            <label for="legal-accepted">I agree to the <a href="{{ \App\Services\LegalPolicies::url('terms',$legal['terms']->version) }}" target="_blank" rel="noopener noreferrer">Terms of Use (v{{ $legal['terms']->version }})</a>@if($venue) and the <a href="{{ \App\Services\VenueContext::url('policies',venue:$venue) }}" target="_blank" rel="noopener noreferrer">venue's booking and cancellation policy</a>@endif.</label>
        </div>
        @if($legal['subscriptionVersion'])
            <div class="legal-check"><input id="subscription-accepted" type="checkbox" name="subscription_accepted" value="1" required><label for="subscription-accepted">I am authorized to agree to the <a href="{{ \App\Services\LegalPolicies::url('subscription-terms',$legal['subscriptionVersion']->version) }}" target="_blank" rel="noopener noreferrer">venue subscription terms (v{{ $legal['subscriptionVersion']->version }})</a> for this venue.</label></div>
        @endif
    @endif
    <p class="legal-notice">
        @if($venue)Your name and mobile number are used to manage this reservation and reconcile payment with the venue.
        @else Your account details are used to administer authorized venue access and secure your account.
        @endif
        Read the <a href="{{ \App\Services\LegalPolicies::url('privacy',$legal['privacy']?->version) }}" target="_blank" rel="noopener noreferrer">Privacy Policy</a>.
    </p>
</div>
