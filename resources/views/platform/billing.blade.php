@extends('layout')
@section('title','Venue subscription')
@section('content')
<header class="section-heading page-heading"><div><a href="{{ route('app.admin.index') }}">← Platform administration</a><h1>{{ $record->name }}</h1><p class="muted">Subscription billing · Separate from player booking payments</p></div></header>
<div class="settings-grid">
    <section class="panel form-stack"><h2>Subscription</h2>@if($subscription?->reward_managed)<p class="notice">Reward-managed term. Access ends at the recorded renewal date unless renewed. <a href="{{ route('app.admin.rewards.index',['tab'=>'ledger','q'=>$record->slug]) }}">Reward ledger</a></p>@endif
        @if($subscription?->trial_ends_at)<p class="muted">Trial ends {{ $subscription->trial_ends_at }} · {{ $subscription->trial_extensions_count }} extensions used.</p>@endif @if($subscription?->upgrade_requested_at)<p class="notice">Plan requested: {{ \App\Services\SubscriptionPlans::LABELS[$subscription->requested_plan ?? 'monthly'] }} · {{ $subscription->upgrade_requested_at }}.</p>@endif<p>Plan: {{ \App\Services\SubscriptionPlans::LABELS[$subscription->billing_plan ?? 'monthly'] }} · Monthly ₱1,000 / Yearly ₱10,000 · Free setup</p>
        <form class="form-stack" method="post" action="{{ route('app.admin.billing.save',$record->id) }}">@csrf @method('PATCH')
            <input type="hidden" name="expected_renewal" value="{{ $subscription->renewal_date ?? '' }}">
            <label>Status<select name="status">@foreach(['onboarding','trial','trial_expired','active','overdue','suspended','cancelled'] as $state)<option value="{{ $state }}" @selected(($subscription->status ?? 'onboarding')===$state)>{{ ucfirst($state) }}</option>@endforeach</select></label>
            <label>Renewal date<input type="date" name="renewal_date" value="{{ old('renewal_date',$subscription->renewal_date ?? '') }}"></label><p class="muted">Self-service venues require active trial or paid access. Legacy venues keep their existing publication rules.</p><button>Save subscription</button>
        </form>
        @if($subscription?->promo_email_attempted_at)<p class="muted">Offer email: {{ $subscription->promo_email_sent_at ? 'Sent '.$subscription->promo_email_sent_at : 'Attempted; check email logs before manually contacting the owner.' }}</p>@endif
        @if($subscription?->promo_redeemed_at)<p class="notice success">First-year offer redeemed {{ $subscription->promo_redeemed_at }}.</p>@endif
    </section>
    <section class="panel form-stack"><h2>Record received payment</h2><p class="muted">Record only payments you have verified. This does not charge the customer.</p>
        @if($subscription)<form class="form-stack" method="post" action="{{ route('app.admin.billing.payment',$record->id) }}">@csrf<label>Payment type<select name="kind"><option value="monthly">Monthly — ₱1,000</option><option value="yearly" @selected($subscription->requested_plan==='yearly')>Yearly — ₱10,000</option>@if(\App\Services\SubscriptionPlans::offerOwner($record,$subscription) && $subscription->requested_plan==='yearly_promo')<option value="yearly_promo" selected>First-year special — ₱5,000</option>@endif</select></label><p class="muted">Verified yearly payments activate a 12-month term after the current valid term. Monthly payments retain manual renewal handling.</p><label>Amount (PHP)<input name="amount" type="number" min=".01" step=".01" required></label><label>Transaction reference<input name="reference" maxlength="100" required></label><label>Date received<input type="date" name="received_on" value="{{ now('Asia/Manila')->toDateString() }}" required></label><button>Record payment</button></form>
        @else<p>Save subscription details first.</p>@endif
    </section>
</div>
<section class="panel tenant-panel"><h2>Payment history</h2><div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Received</th><th>Type</th><th>Reference</th><th>Amount</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->received_on }}</td><td>{{ $payment->kind === 'setup' ? 'Historical setup payment' : ucfirst($payment->kind) }}</td><td>{{ $payment->reference }}</td><td>₱{{ number_format($payment->amount/100,2) }}</td></tr>@empty<tr><td colspan="4">No payments recorded.</td></tr>@endforelse</tbody></table></div>@if($subscription){{ $payments->links() }}@endif</section>
@endsection
