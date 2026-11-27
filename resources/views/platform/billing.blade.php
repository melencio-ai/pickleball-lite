@extends('layout')
@section('title','Venue subscription')
@section('content')
<header class="section-heading page-heading"><div><a href="{{ route('app.admin.index') }}">← Platform administration</a><h1>{{ $record->name }}</h1><p class="muted">Subscription billing · Separate from player booking payments</p></div></header>
<div class="settings-grid">
    <section class="panel form-stack"><h2>Subscription</h2>@if($subscription?->reward_managed)<p class="notice">Reward-managed term. Access ends at the recorded renewal date unless renewed. <a href="{{ route('app.admin.rewards.index',['tab'=>'ledger','q'=>$record->slug]) }}">Reward ledger</a></p>@endif
        @if($subscription?->trial_ends_at)<p class="muted">Trial ends {{ $subscription->trial_ends_at }} · {{ $subscription->trial_extensions_count }} extensions used.</p>@endif @if($subscription?->upgrade_requested_at)<p class="notice">Paid upgrade requested {{ $subscription->upgrade_requested_at }}.</p>@endif<p>Monthly: ₱{{ number_format(($subscription->monthly_fee ?? 100000)/100,2) }} · Free setup</p>
        <form class="form-stack" method="post" action="{{ route('app.admin.billing.save',$record->id) }}">@csrf @method('PATCH')
            <input type="hidden" name="expected_renewal" value="{{ $subscription->renewal_date ?? '' }}">
            <label>Status<select name="status">@foreach(['onboarding','trial','trial_expired','active','overdue','suspended','cancelled'] as $state)<option value="{{ $state }}" @selected(($subscription->status ?? 'onboarding')===$state)>{{ ucfirst($state) }}</option>@endforeach</select></label>
            <label>Renewal date<input type="date" name="renewal_date" value="{{ old('renewal_date',$subscription->renewal_date ?? '') }}"></label><p class="muted">Self-service venues require active trial or paid access. Legacy venues keep their existing publication rules.</p><button>Save subscription</button>
        </form>
    </section>
    <section class="panel form-stack"><h2>Record received payment</h2><p class="muted">Record only payments you have verified. This does not charge the customer.</p>
        @if($subscription)<form class="form-stack" method="post" action="{{ route('app.admin.billing.payment',$record->id) }}">@csrf<input type="hidden" name="kind" value="monthly"><p>Payment type: Monthly maintenance</p><label>Amount (PHP)<input name="amount" type="number" min=".01" step=".01" required></label><label>Transaction reference<input name="reference" maxlength="100" required></label><label>Date received<input type="date" name="received_on" value="{{ now('Asia/Manila')->toDateString() }}" required></label><button>Record payment</button></form>
        @else<p>Save subscription details first.</p>@endif
    </section>
</div>
<section class="panel tenant-panel"><h2>Payment history</h2><div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Received</th><th>Type</th><th>Reference</th><th>Amount</th></tr></thead><tbody>@forelse($payments as $payment)<tr><td>{{ $payment->received_on }}</td><td>{{ $payment->kind === 'setup' ? 'Historical setup payment' : ucfirst($payment->kind) }}</td><td>{{ $payment->reference }}</td><td>₱{{ number_format($payment->amount/100,2) }}</td></tr>@empty<tr><td colspan="4">No payments recorded.</td></tr>@endforelse</tbody></table></div>@if($subscription){{ $payments->links() }}@endif</section>
@endsection
