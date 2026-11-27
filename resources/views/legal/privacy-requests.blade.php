@extends('layout')
@section('title','Privacy requests')
@section('content')
<header class="section-heading page-heading"><div><h1>Privacy requests</h1><p class="muted">{{ $venue ? $venue->name : 'Platform and venue requests' }} · Record and track data subject requests.</p></div>@if(!$venue)<a class="button secondary" href="{{ route('app.admin.legal.index') }}">Legal policies</a>@endif</header>
<div class="notice">Verify the requester's identity and authority before releasing or changing records. Log only a necessary contact or internal case reference; do not enter identity document images, passwords, or sensitive details. Changing the status does not automatically export or delete personal data.</div>
<section class="panel form-stack">
    <h2>Record a request</h2>
    <form class="form-stack" method="post" action="{{ $venue ? \App\Services\VenueContext::url('settings.privacy.store') : route('app.admin.privacy.store') }}">
        @csrf
        <div class="tenant-form-grid">
            @unless($venue)<label>Scope<select name="venue_id"><option value="">Platform</option>@foreach($venues as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach</select></label>@endunless
            <label>Request type<select name="kind">@foreach($kinds as $kind)<option value="{{ $kind }}">{{ ucfirst($kind) }}</option>@endforeach</select></label>
            <label>Contact or internal case reference<input name="contact" required maxlength="255"></label>
            <label>Date received<input name="received_on" type="date" value="{{ now()->toDateString() }}" required></label>
        </div>
        <button>Record request</button>
    </form>
</section>
<section class="panel legal-section">
<div class="section-heading"><h2>Requests</h2><form class="actions" method="get" action="{{ $destination }}"><label>Status<select name="status"><option value="">All</option>@foreach($statuses as $item)<option value="{{ $item }}" @selected($status===$item)>{{ ucfirst(str_replace('_',' ',$item)) }}</option>@endforeach</select></label><button class="secondary">Filter</button></form></div>
<div class="legal-history"><table class="tenant-table"><thead><tr><th>Reference</th><th>Scope</th><th>Type / contact</th><th>Received</th><th>Status</th></tr></thead><tbody>
@forelse($records as $record)
<tr><td>PR-{{ $record->id }}</td><td>{{ $record->venue_name ?? 'Platform' }}</td><td>{{ ucfirst($record->kind) }}<br><span class="muted">{{ $record->contact }}</span></td><td>{{ $record->received_on }}</td><td>
<form class="actions" method="post" action="{{ $venue ? \App\Services\VenueContext::url('settings.privacy.update',['record'=>$record->id]) : route('app.admin.privacy.update',$record->id) }}">@csrf @method('PATCH')
<label><span class="visually-hidden">Request status</span><select name="status">@foreach($statuses as $item)<option value="{{ $item }}" @selected($record->status===$item)>{{ ucfirst(str_replace('_',' ',$item)) }}</option>@endforeach</select></label><button class="secondary small">Save</button></form>
</td></tr>
@empty<tr><td colspan="5">No requests found.</td></tr>@endforelse
</tbody></table></div>{{ $records->links() }}</section>
@endsection
