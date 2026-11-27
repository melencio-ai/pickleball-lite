@props(['type','record'])
@php($acceptedVersions = \Illuminate\Support\Facades\DB::table('legal_acceptances')->join('legal_document_versions','legal_document_versions.id','=','legal_acceptances.legal_document_version_id')->where('legal_acceptances.subject_type',$type)->where('legal_acceptances.subject_id',$record->id)->select('legal_document_versions.document_slug','legal_document_versions.version')->get())
@if($acceptedVersions->isNotEmpty())
    <div class="legal-notice">Policies recorded for this reservation:
        @foreach($acceptedVersions as $version)
            <a href="{{ \App\Services\LegalPolicies::url($version->document_slug,$version->version) }}" target="_blank" rel="noopener noreferrer">{{ $version->document_slug === 'terms' ? 'Terms of Use' : 'Privacy Policy' }} (v{{ $version->version }})</a>@unless($loop->last) · @endunless
        @endforeach
    </div>
@endif
