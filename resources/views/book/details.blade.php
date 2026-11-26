<section class="booking-details" id="booking-form" tabindex="-1" aria-labelledby="booking-details-title">
    <div class="section-heading">
        <div>
            <h2 id="booking-details-title">Add your details</h2>
            <p>{{ $selection['court']->name }} · {{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }} · {{ $selection['slots'][$selectedStart]['label'] }} · {{ $duration }} min</p>
        </div>
        <strong class="price">₱{{ number_format($selection['slots'][$selectedStart]['total'] / 100, 2) }}</strong>
    </div>
    @include('book.errors')
    <form class="form-row" method="post" action="{{ route('book.store') }}" data-booking-form>
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">
        <input type="hidden" name="duration" value="{{ $duration }}">
        <input type="hidden" name="court_id" value="{{ $selection['court']->id }}">
        <input type="hidden" name="start" value="{{ $selectedStart }}">
        <input type="hidden" name="quoted_total" value="{{ $selection['slots'][$selectedStart]['total'] }}">
        <label>Full name<input name="customer_name" value="{{ is_string(old('customer_name')) ? old('customer_name') : '' }}" maxlength="100" autocomplete="name" required></label>
        <label>Mobile number<input name="customer_phone" type="tel" value="{{ is_string(old('customer_phone')) ? old('customer_phone') : '' }}" maxlength="32" autocomplete="tel" required></label>
        @if($venue->cancellation_policy)
            <details class="compact-policy">
                <summary>Cancellation policy <x-booking-icon name="chevron-down" /></summary>
                <p>{{ $venue->cancellation_policy }}</p>
            </details>
        @endif
        <button class="booking-button" data-booking-submit>
            @if($venue->qr_payments_enabled)<x-booking-icon name="qr-code" /> Continue to QR payment
            @else Confirm booking
            @endif
        </button>
    </form>
</section>
