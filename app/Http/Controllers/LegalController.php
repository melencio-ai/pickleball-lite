<?php

namespace App\Http\Controllers;

use App\Services\AuthDestination;
use App\Services\LegalPolicies;
use App\Services\VenueContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegalController extends Controller
{
    public function show(Request $request, string $document)
    {
        abort_unless(in_array($document,LegalPolicies::SLUGS,true),404);
        $definition = DB::table('legal_documents')->where('slug',$document)->firstOrFail();
        $query = DB::table('legal_document_versions')->where('document_slug',$document);
        if ($request->has('version')) {
            $version = $request->validate(['version'=>'required|integer|min:1'])['version'];
            $record = $query->where('version',$version)->firstOrFail();
        } else {
            $record = $definition->current_version_id ? $query->where('id',$definition->current_version_id)->firstOrFail() : null;
        }
        return response()->view('legal.public',compact('definition','record'))
            ->header('Cache-Control','no-store');
    }

    public function venue()
    {
        $venue = VenueContext::venue();
        $policy = LegalPolicies::venuePolicy($venue);
        return view('legal.venue',compact('venue','policy'));
    }

    public function acceptPage(Request $request)
    {
        if (!LegalPolicies::needsAcceptance($request->user())) {
            return redirect()->to(AuthDestination::home($request->user()));
        }
        $legal = LegalPolicies::context();
        return response()->view('legal.accept',compact('legal'))
            ->withHeaders(['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer']);
    }

    public function accept(Request $request)
    {
        DB::transaction(function () use ($request) {
            $context = LegalPolicies::context(lock:true);
            LegalPolicies::validate($request->only('legal_fingerprint','legal_accepted'),$context);
            LegalPolicies::record($context,'user',$request->user()->id,null,$request->user()->id);
        },3);
        // AuthDestination validates same-origin protected destinations.
        $request->session()->put('url.intended',$request->session()->pull('legal.intended'));
        return redirect()->to(AuthDestination::afterLogin($request,$request->user()));
    }
}
