<?php

namespace App\Http\Controllers;

use App\Services\LegalPolicies;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LegalAdminController extends Controller
{
    public static function draftFingerprint($documents): string
    {
        return hash('sha256',json_encode($documents->map(fn ($d)=>[$d->slug,$d->draft_body])->values(),JSON_THROW_ON_ERROR));
    }

    public function index()
    {
        $documents = DB::table('legal_documents')->orderBy('slug')->get();
        $current = LegalPolicies::current();
        $history = DB::table('legal_document_versions')->orderByDesc('id')->paginate(24);
        $fingerprint = self::draftFingerprint($documents);
        $details = $current->isNotEmpty() ? json_decode($current->first()->operator_details,true,512,JSON_THROW_ON_ERROR) : [];
        $counts = DB::table('legal_acceptances')->selectRaw('legal_document_version_id, count(*) as total')->groupBy('legal_document_version_id')->pluck('total','legal_document_version_id');
        return view('legal.admin',compact('documents','current','history','fingerprint','details','counts'));
    }

    public function save(Request $request,string $document)
    {
        abort_unless(in_array($document,LegalPolicies::SLUGS,true),404);
        $data = $request->validate(['body'=>'required|string|max:30000']);
        DB::transaction(function () use ($request,$data,$document) {
            $record = DB::table('legal_documents')->where('slug',$document)->lockForUpdate()->firstOrFail();
            DB::table('legal_documents')->where('slug',$record->slug)->update(['draft_body'=>$data['body'],'updated_at'=>now()]);
            VenueInvitations::audit($request->user(),null,'legal.draft_saved',['document'=>$document]);
        },3);
        return redirect()->route('app.admin.legal.index')->with('status','Draft saved. Published policies are unchanged.');
    }

    public function publish(Request $request)
    {
        $data = $request->validate([
            'legal_name'=>'required|string|max:200','business_address'=>'required|string|max:500',
            'support_email'=>'required|email|max:255','privacy_email'=>'required|email|max:255',
            'retention'=>'required|string|max:5000','commercial_conditions'=>'required|string|max:5000',
            'draft_fingerprint'=>'required|string|size:64','confirmed'=>'accepted',
        ]);
        $details = array_intersect_key($data,array_flip(['legal_name','business_address','support_email','privacy_email','retention','commercial_conditions']));
        DB::transaction(function () use ($request,$data,$details) {
            $documents = DB::table('legal_documents')->orderBy('slug')->lockForUpdate()->get();
            if ($documents->count() !== count(LegalPolicies::SLUGS) || !hash_equals(self::draftFingerprint($documents),$data['draft_fingerprint'])) {
                throw ValidationException::withMessages(['draft_fingerprint'=>'Drafts changed. Review them before publishing.']);
            }
            $ids = [];
            foreach ($documents as $document) {
                $version = (int) DB::table('legal_document_versions')->where('document_slug',$document->slug)->max('version') + 1;
                $body = trim($document->draft_body);
                if (str_contains($body,'[REVIEW') || str_contains($body,'[TODO') || $body === '') {
                    throw ValidationException::withMessages(['confirmed'=>'Resolve all draft review markers before publishing.']);
                }
                if ($document->slug === 'privacy') $body .= "\n\nRetention schedule\n".$details['retention'];
                if ($document->slug === 'subscription-terms') $body .= "\n\nCommercial conditions\n".$details['commercial_conditions'];
                $body .= "\n\nPlatform operator and contact\n".$details['legal_name']."\n".$details['business_address']."\nSupport: ".$details['support_email']."\nPrivacy: ".$details['privacy_email'];
                $id = DB::table('legal_document_versions')->insertGetId([
                    'document_slug'=>$document->slug,'version'=>$version,'body'=>$body,
                    'operator_details'=>json_encode($details,JSON_THROW_ON_ERROR),'published_by'=>$request->user()->id,'published_at'=>now(),
                ]);
                DB::table('legal_documents')->where('slug',$document->slug)->update(['current_version_id'=>$id,'updated_at'=>now()]);
                $ids[$document->slug] = $id;
            }
            VenueInvitations::audit($request->user(),null,'legal.bundle_published',['versions'=>$ids]);
        },3);
        return redirect()->route('app.legal.accept')->with('status','Policies published. Review and accept the new terms to continue.');
    }
}
