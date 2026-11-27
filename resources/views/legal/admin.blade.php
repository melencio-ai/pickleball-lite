@extends('layout')
@section('title','Legal policies')
@section('content')
<header class="section-heading page-heading"><div><h1>Legal policies</h1><p class="muted">Review, version and publish the platform's agreements and privacy notices.</p></div><a class="button secondary" href="{{ route('app.admin.privacy.index') }}">Privacy requests</a></header>
@if($current->isEmpty())<div class="notice">Draft mode: the public legal pages are not yet published. Existing bookings and accounts remain operational. Confirm the operator's identity, data retention and commercial terms before publishing.</div>@endif
<div class="legal-admin-grid">
@foreach($documents as $document)
    @php($version = $current->get($document->slug))
    <section class="panel legal-editor">
        <div class="section-heading"><h2>{{ $document->title }}</h2><span class="legal-state">{{ $version ? 'Live v'.$version->version : 'Draft' }}</span></div>
        @if($version)<p class="muted">{{ $counts[$version->id] ?? 0 }} recorded acknowledgements / notices</p><a href="{{ \App\Services\LegalPolicies::url($document->slug,$version->version) }}" target="_blank" rel="noopener noreferrer">View published version</a>@endif
        <details><summary>Edit next draft</summary>
            <form class="form-stack" method="post" action="{{ route('app.admin.legal.save',$document->slug) }}">
                @csrf @method('PATCH')
                <label>Draft content<textarea name="body" required maxlength="30000" rows="12">{{ $document->draft_body }}</textarea></label>
                <button>Save draft</button>
            </form>
        </details>
    </section>
@endforeach
</div>
<section class="panel legal-section" id="publish"><h2>Review and publish</h2><p class="muted">Publishing creates a new immutable version of all four documents. New online reservations and invitations will require the relevant acceptance. Existing staff will be asked to review the new terms. Past reservations and published versions remain unchanged.</p>
<form class="form-stack" method="post" action="{{ route('app.admin.legal.publish') }}">
    @csrf
    <input type="hidden" name="draft_fingerprint" value="{{ $fingerprint }}">
    <div class="tenant-form-grid">
        <label>Registered operator name<input name="legal_name" required maxlength="200" value="{{ old('legal_name',$details['legal_name'] ?? '') }}"></label>
        <label>Registered business address<input name="business_address" required maxlength="500" value="{{ old('business_address',$details['business_address'] ?? '') }}"></label>
        <label>Support email<input name="support_email" type="email" required value="{{ old('support_email',$details['support_email'] ?? '') }}"></label>
        <label>Privacy / data protection contact email<input name="privacy_email" type="email" required value="{{ old('privacy_email',$details['privacy_email'] ?? '') }}"></label>
    </div>
    <label>Approved data retention schedule<textarea name="retention" required maxlength="5000" rows="5" placeholder="Specify periods for booking, account, financial, enquiry, session, and log data and applicable exceptions.">{{ old('retention',$details['retention'] ?? '') }}</textarea></label>
    <label>Agreed commercial conditions<textarea name="commercial_conditions" required maxlength="5000" rows="5" placeholder="Specify setup deliverables, due dates, grace period, cancellation, refunds, support and data export/termination.">{{ old('commercial_conditions',$details['commercial_conditions'] ?? '') }}</textarea></label>
    <div class="legal-check"><input id="publish-confirmed" name="confirmed" type="checkbox" value="1" required><label for="publish-confirmed">I am authorized to publish these policies. I have reviewed every draft, verified the business and processing details, and approved the commercial conditions and applicable legal requirements.</label></div>
    <button type="submit">Publish all four policies</button>
</form>
</section>
<section class="panel legal-section"><h2>Published history</h2><div class="legal-history"><table class="tenant-table"><thead><tr><th>Document</th><th>Version</th><th>Published</th><th>Records</th><th>View</th></tr></thead><tbody>
@forelse($history as $row)<tr><td>{{ $row->document_slug }}</td><td>{{ $row->version }}</td><td>{{ \Carbon\CarbonImmutable::parse($row->published_at)->format('M j, Y H:i') }}</td><td>{{ $counts[$row->id] ?? 0 }}</td><td><a href="{{ \App\Services\LegalPolicies::url($row->document_slug,$row->version) }}" target="_blank" rel="noopener noreferrer">Open</a></td></tr>
@empty<tr><td colspan="5">No policies published.</td></tr>@endforelse
</tbody></table></div>{{ $history->links() }}</section>
@endsection
