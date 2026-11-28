@extends('layout')
@section('title','Subscription payments')
@section('content')
<header class="section-heading page-heading"><div><h1>Subscription payments</h1><p class="muted">Platform billing only. Verify actual receipts against your bank account before approving.</p></div><span class="status-chip">{{ $settings->enabled?'QR enabled':'QR disabled' }}</span></header>
<section class="panel form-stack">
    <h2>Platform payment QR</h2>
    <p class="muted">This is the Pickle Lite recipient, not any venue's booking QR. Existing open requests retain their original payment QR.</p>
    @if($preview)
        <details><summary>Preview QR for ₱1,000 (test only)</summary>
            <p>Scan and check the intended recipient and exact amount. Confirm with your payment provider that changed amounts and repeated uses of this dynamic code are supported. A scan alone does not establish that payments settle.</p>
            <div class="payment-qr-block"><canvas class="payment-qr" data-payment-qr="{{ $preview }}" aria-label="Platform payment QR preview"></canvas><a class="button secondary" data-payment-download download="platform-test-qr.png" hidden>Save test QR</a></div>
            <script src="{{ asset('vendor/qrcode-generator-1.4.4.js') }}" defer></script><script src="{{ asset('payment-qr.js') }}?v=4" defer></script>
        </details>
    @endif
    <form class="form-stack" method="post" action="{{ route('app.admin.subscription-payments.settings') }}">@csrf @method('PATCH')
        <label>Replace base EMV QR <textarea name="base" rows="3" maxlength="512" autocomplete="off" placeholder="{{ $preview?'Configured — leave blank to keep':'Paste base EMV code' }}"></textarea></label>
        <label class="checkbox subscription-checkbox"><input type="checkbox" name="enabled" value="1" @checked($settings->enabled)><span>Enable venue subscription QR payments</span></label>
        <label class="checkbox subscription-checkbox"><input type="checkbox" name="provider_confirmed" value="1"><span>I have verified the payment recipient and provider support for amount-adjusted reusable QR codes.</span></label>
        <button>Save payment settings</button>
    </form>
    <x-messenger-link class="button secondary" :url="\App\Services\MessengerLinks::PLATFORM">Open Pickle Lite Messenger</x-messenger-link>
</section>
<section class="panel tenant-panel">
    <div class="section-heading"><h2>Verification queue</h2><form method="get" class="actions"><label>Status<select name="status">@foreach(['pending'=>'Open requests','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled','all'=>'All'] as $key=>$label)<option value="{{ $key }}" @selected($filter===$key)>{{ $label }}</option>@endforeach</select></label><button class="secondary">Filter</button></form></div>
    @forelse($payments as $payment)
        <article class="panel form-stack">
            <div class="section-heading"><div><strong>{{ $payment->venue_name }}</strong><p class="muted">{{ $payment->owner_name }} · {{ $payment->reference }}</p></div><span class="status-chip">{{ ucfirst(str_replace('_',' ',$payment->status)) }}</span></div>
            <p><strong>₱{{ number_format($payment->amount/100,2) }}</strong> · {{ \App\Services\SubscriptionPlans::LABELS[$payment->plan] }} · {{ $payment->created_at }}</p>
            <a href="{{ route('app.admin.billing',$payment->venue_id) }}">Venue billing</a>
            @if(in_array($payment->status,\App\Services\PlatformSubscriptionQr::OPEN,true))
                <details><summary>Verify payment</summary>
                    <form class="form-stack" method="post" action="{{ route('app.admin.subscription-payments.review',$payment->id) }}">@csrf
                        <input type="hidden" name="decision" value="approve">
                        <label>Amount actually received (PHP)<input name="amount" type="number" min=".01" step=".01" required></label>
                        <label>Bank / wallet transaction reference<input name="bank_reference" maxlength="100" required></label>
                        <label>Date received<input name="received_on" type="date" required value="{{ now('Asia/Manila')->toDateString() }}" max="{{ now('Asia/Manila')->toDateString() }}"></label>
                        <label class="checkbox subscription-checkbox"><input type="checkbox" name="verified_in_messenger" value="1" required><span>I verified the Messenger receipt and matched the amount and reference to the actual received funds.</span></label>
                        <button>Approve and extend subscription</button>
                    </form>
                </details>
                <details><summary>Reject request</summary><form class="form-stack" method="post" action="{{ route('app.admin.subscription-payments.review',$payment->id) }}">@csrf<input type="hidden" name="decision" value="reject"><label>Reason shown to owner<textarea name="reason" maxlength="500" required></textarea></label><p class="muted">Resolve any funds received before rejecting.</p><button class="secondary">Reject request</button></form></details>
            @else
                @if($payment->bank_reference)<p>Verified reference: {{ $payment->bank_reference }}</p>@endif
                @if($payment->review_note)<p>{{ $payment->review_note }}</p>@endif
            @endif
        </article>
    @empty<p class="muted">No payment requests match this filter.</p>@endforelse
    {{ $payments->links() }}
</section>
@endsection
