@extends('layout')
@section('title','Set up your venue')
@section('page-class','onboarding-page')
@section('content')
@php
$titles=['Tell us about your venue','How many courts do you have?','When are you open?','How much do you charge?','How will players pay?','Make it your own','Review and launch'];
$descriptions=['Create your venue identity and booking link.','Start with numbered courts. You can rename them later in Settings.','Choose regular operating days and times. You can customize individual days later.','Set the starting rate per hour and supported booking durations.','Choose pay at venue or QR payment with Messenger verification.','Add your own branding. This step is optional.','Confirm your details and publish your booking website.'];
@endphp
<section class="panel onboarding-panel">
    <div class="section-heading"><span class="muted">Step {{ $step }} of 7</span><span class="muted">{{ round($step/7*100) }}%</span></div>
    <div class="onboard-progress" aria-label="Step {{ $step }} of 7">@foreach(range(1,7) as $i)<span class="{{ $i<=$step?'complete':'' }}"></span>@endforeach</div>
    <h1>{{ $titles[$step-1] }}</h1><p class="muted">{{ $descriptions[$step-1] }}</p>
    <form method="post" action="{{ route('app.settings.onboarding.save',['venueSlug'=>$venue->slug,'step'=>$step]) }}" class="form-stack onboarding-form" enctype="multipart/form-data" data-app-form>
        @csrf
        @switch($step)
        @case(1)
            <label>Venue name<input name="name" required maxlength="100" value="{{ old('name',$venue->name==='Your venue'?'':$venue->name) }}" autocomplete="organization"></label>
            <label>Booking link name<input name="slug" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" value="{{ old('slug',$venue->name==='Your venue'?'':$venue->slug) }}" placeholder="my-pickleball-courts"><small>Use lowercase letters, numbers and hyphens.</small></label>
            <label>Address<input name="address" required maxlength="255" value="{{ old('address',$venue->address) }}" autocomplete="street-address"></label>
            <label>Contact number<input name="phone" type="tel" required value="{{ old('phone',$venue->phone) }}" autocomplete="tel"></label>
            <label>Time zone<input name="timezone" required value="{{ old('timezone',$venue->timezone) }}"></label>
            @break
        @case(2)
            <label>Number of courts<input name="court_count" type="number" min="1" max="30" value="{{ old('court_count',max(1,$courts->count())) }}" required></label>
            @break
        @case(3)
            <fieldset><legend>Days open</legend><div class="onboard-options">@foreach(['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day=>$label)<label class="checkbox"><input type="checkbox" name="days[]" value="{{ $day }}" @checked(in_array((string)$day,array_map('strval',old('days',$hours->where('closed',false)->keys()->all() ?: range(0,6)))))> {{ $label }}</label>@endforeach</div></fieldset>
            @php($firstHour=$hours->where('closed',false)->first())
            <label>Opening time<input name="opens_at" required value="{{ old('opens_at',$firstHour?->opens_at ?? '07:00') }}" pattern="[0-9]{2}:[0-9]{2}" placeholder="07:00"></label>
            <label>Closing time<input name="closes_at" required value="{{ old('closes_at',$firstHour?->closes_at ?? '22:00') }}" pattern="[0-9]{2}:[0-9]{2}" placeholder="22:00"><small>24-hour time, on the half hour. Use 24:00 for midnight.</small></label>
            @break
        @case(4)
            <label>Hourly rate (PHP)<input name="rate" type="number" min=".01" max="999999.99" step=".01" required value="{{ old('rate',number_format(($courts->first()?->hourly_rate ?? 40000)/100,2,'.','')) }}"></label>
            <fieldset><legend>Available booking durations</legend><div class="onboard-options">@foreach(\App\Models\Venue::BOOKING_DURATIONS as $minutes)<label class="checkbox"><input type="checkbox" name="durations[]" value="{{ $minutes }}" @checked(in_array((string)$minutes,array_map('strval',old('durations',$venue->durations()))))>{{ \App\Models\Venue::durationLabel($minutes) }}</label>@endforeach</div></fieldset>
            @break
        @case(5)
            <label>Payment method<select name="payment_method" data-onboard-payment><option value="venue" @selected(old('payment_method',$venue->qr_payments_enabled?'qr':'venue')==='venue')>Pay at venue</option><option value="qr" @selected(old('payment_method',$venue->qr_payments_enabled?'qr':'venue')==='qr')>Merchant QR and Messenger</option></select></label>
            <div class="form-stack" data-onboard-qr>
                <label>Merchant EMV QR code<textarea name="payment_qr_base" maxlength="512" rows="4">{{ old('payment_qr_base',$venue->payment_qr_base) }}</textarea></label>
                <label>Payment verification Messenger link<input name="payment_messenger_url" type="url" maxlength="255" value="{{ old('payment_messenger_url',$venue->payment_messenger_url) }}" placeholder="https://m.me/yourvenue"></label>
                <label class="checkbox"><input type="checkbox" name="provider_confirmed" value="1"> I have authorization to use this merchant QR and have verified the amount generation with my provider.</label>
            </div>
            @break
        @case(6)
            <label>Brand color<input type="color" name="brand_color" value="{{ old('brand_color',$venue->brand_color ?? '#155542') }}"></label>
            <label>Venue logo<input type="file" name="logo" accept="image/jpeg,image/png,image/webp"><small>Up to 1 MB.</small></label>
            <label>Cover photo<input type="file" name="cover" accept="image/jpeg,image/png,image/webp"><small>Up to 2 MB.</small></label>
            <label>Venue Messenger link<input name="messenger_url" type="url" value="{{ old('messenger_url',$venue->messenger_url) }}"></label>
            @break
        @case(7)
            <div class="onboard-summary"><strong>{{ $venue->name }}</strong><span>{{ $venue->address }}</span><span>{{ $courts->count() }} courts · ₱{{ number_format(($courts->first()?->hourly_rate ?? 0)/100,2) }}/hour</span><span>{{ $hours->where('closed',false)->count() }} open days · {{ $venue->qr_payments_enabled?'Merchant QR':'Pay at venue' }}</span><a href="{{ route('app.settings.website.preview',['venueSlug'=>$venue->slug]) }}" target="_blank" rel="noopener">Preview booking website</a><span class="muted" style="overflow-wrap:anywhere">{{ route('public.landing',['venueSlug'=>$venue->slug]) }}</span></div>
            <label>Booking and cancellation policy<textarea name="cancellation_policy" rows="4" maxlength="1500" required>{{ old('cancellation_policy',$venue->cancellation_policy) }}</textarea></label>
            <label class="checkbox"><input type="checkbox" name="launch_confirmed" value="1" required> My venue details, rates, payment instructions and cancellation policy are correct.</label>
            @break
        @endswitch
        <div class="onboard-actions">
            @if($step>1)<a class="button secondary" href="{{ route('app.settings.onboarding',['venueSlug'=>$venue->slug,'step'=>$step-1]) }}">Back</a>@endif
            <button type="submit" data-app-submit>{{ $step===7?'Launch my venue':'Continue' }}</button>
        </div>
    </form>
    <p class="muted onboard-saved">Your completed steps are saved. You can sign out and continue later.</p>
</section>
@endsection
