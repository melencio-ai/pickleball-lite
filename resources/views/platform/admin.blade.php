@extends('layout')
@section('title','Platform administration')
@section('content')
<header class="section-heading page-heading"><div><h1>Platform administration</h1><p class="muted">Manage venues, owner access, subscriptions and enquiries.</p></div><div class="actions"><a class="button secondary" href="{{ route('app.admin.subscription-payments.index') }}">Subscription payments</a><a class="button secondary" href="{{ route('app.admin.legal.index') }}">Legal policies</a><a class="button secondary" href="{{ route('app.admin.privacy.index') }}">Privacy requests</a><a class="button" href="#new-venue">+ New venue</a></div></header>
<div class="tenant-stats">
    @foreach(['Venues'=>$stats['venues'],'Published venues'=>$stats['active'],'Active subscription MRR'=>'₱'.number_format($stats['mrr']/100,2),'Overdue subscriptions'=>$stats['overdue']] as $label=>$value)
        <div class="panel"><span class="muted">{{ $label }}</span><strong>{{ $value }}</strong></div>
    @endforeach
</div>
@if(session('invite_url'))
    <section class="panel form-stack"><h2>Private invitation</h2><p>Send this one-time link privately to the intended recipient. It expires in 72 hours. It is not sent automatically.</p><label>Invitation link<input value="{{ session('invite_url') }}" readonly onclick="this.select()"></label></section>
@endif
<section class="panel tenant-panel">
    <div class="section-heading"><h2>Venues</h2><form class="actions" method="get" action="{{ route('app.admin.index') }}"><label class="visually-hidden" for="venue-search">Search venues</label><input id="venue-search" name="q" value="{{ $search }}" placeholder="Search name or slug"><button class="secondary">Search</button></form></div>
    <div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Venue</th><th>Members</th><th>Website status</th><th>Subscription</th><th>Actions</th></tr></thead><tbody>
    @forelse($venues as $record)
        <tr><td><strong>{{ $record->name }}</strong><br><span class="muted">{{ $record->slug }}</span></td><td>{{ $record->members_count }}</td><td>{{ ucfirst($record->status) }}<br><span class="muted">{{ $record->published ? 'Published' : 'Unpublished' }}</span></td><td>{{ ucfirst($record->billing_status ?? 'onboarding') }}<br><span class="muted">{{ $record->renewal_date ?? 'No renewal date' }} @if($record->upgrade_requested_at)<br><strong>{{ \App\Services\SubscriptionPlans::LABELS[$record->requested_plan ?? 'monthly'] }} requested</strong>@endif</span></td>
            <td><div class="actions">@if($record->acceptsPublicBookings())<a href="{{ route('public.landing',['venueSlug'=>$record->slug]) }}" target="_blank" rel="noopener">Website</a>@endif<a href="{{ route('app.admin.billing',$record->id) }}">Billing</a></div>
            <details><summary>Manage</summary><form class="form-stack" method="post" action="{{ route('app.admin.venues.status',$record->id) }}">@csrf @method('PATCH')
                <label>Status<select name="status">@foreach(['onboarding','active','suspended','cancelled'] as $state)<option value="{{ $state }}" @selected($state===$record->status)>{{ ucfirst($state) }}</option>@endforeach</select></label>
                <label class="checkbox"><input type="checkbox" name="published" value="1" @checked($record->published)> Publish website</label><button class="small">Save status</button></form>
                <form class="form-stack settings-row" method="post" action="{{ route('app.admin.venues.invite',$record->id) }}">@csrf<label>Owner email<input name="email" type="email" required></label><button class="secondary small">Create owner invitation</button></form>
            </details></td></tr>
    @empty<tr><td colspan="5">No venues found.</td></tr>@endforelse
    </tbody></table></div>
    <div class="tenant-pagination">{{ $venues->onEachSide(1)->links() }}</div>
</section>
<section class="panel form-stack tenant-panel" id="new-venue"><h2>New venue</h2><p class="muted">Create an unpublished venue. The owner configures courts, hours, rates and payment settings before publication.</p>
    <form class="form-stack" method="post" action="{{ route('app.admin.venues.create') }}">@csrf
        <div class="tenant-form-grid"><label>Venue name<input name="name" value="{{ old('name') }}" required maxlength="100"></label><label>Venue slug<input name="slug" value="{{ old('slug') }}" required maxlength="100" pattern="[a-z0-9]+(-[a-z0-9]+)*" placeholder="my-pickleball-venue"></label><label>Owner email<input name="owner_email" type="email" value="{{ old('owner_email') }}" required></label><label>Time zone<input name="timezone" value="{{ old('timezone','Asia/Manila') }}" required></label><label>Address<input name="address" value="{{ old('address') }}"></label><label>Phone<input name="phone" value="{{ old('phone') }}"></label></div>
        <button>Create venue and invitation</button>
    </form>
</section>
<section class="panel tenant-panel"><h2>Recent product enquiries</h2>
    <div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Venue</th><th>Contact</th><th>Message</th><th>Received</th></tr></thead><tbody>
    @forelse($enquiries as $lead)<tr><td>{{ $lead->venue_name }}</td><td>{{ $lead->name }}<br><a href="mailto:{{ $lead->email }}">{{ $lead->email }}</a><br>{{ $lead->phone }}</td><td>{{ $lead->message }}</td><td>{{ \Carbon\CarbonImmutable::parse($lead->created_at)->format('M j, Y') }}</td></tr>
    @empty<tr><td colspan="4">No enquiries yet.</td></tr>@endforelse</tbody></table></div>
</section>
<section class="panel tenant-panel"><h2>Recent administration activity</h2><div class="tenant-table-scroll"><table class="tenant-table"><thead><tr><th>Action</th><th>Venue ID</th><th>Actor ID</th><th>When</th></tr></thead><tbody>@forelse($audit as $log)<tr><td>{{ $log->action }}</td><td>{{ $log->venue_id ?? '—' }}</td><td>{{ $log->actor_id }}</td><td>{{ $log->created_at }}</td></tr>@empty<tr><td colspan="4">No administrative activity yet.</td></tr>@endforelse</tbody></table></div></section>
@endsection
