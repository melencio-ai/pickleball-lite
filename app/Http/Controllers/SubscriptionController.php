<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\TrialAccess;
use App\Services\VenueContext;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    public function index()
    {
        $venue=VenueContext::venue();
        $subscription=TrialAccess::subscription($venue);
        $canExtend=$venue->self_service && TrialAccess::canExtend($subscription);
        return view('platform.subscription',compact('venue','subscription','canExtend'));
    }

    public function extend(Request $request)
    {
        $data=$request->validate(['extension_count'=>'required|integer|between:0,2']);
        TrialAccess::extend(VenueContext::venue(),$request->user(),(int)$data['extension_count']);
        return VenueContext::redirect('settings.subscription')->with('status','Your trial was extended by 15 days.');
    }

    public function upgrade(Request $request)
    {
        DB::transaction(function() use($request) {
            $venue=Venue::whereKey(VenueContext::venue()->id)->lockForUpdate()->firstOrFail();
            $sub=TrialAccess::subscription($venue,true);
            abort_unless($sub,404);
            if(!$sub->upgrade_requested_at && $sub->status!=='active') {
                DB::table('venue_subscriptions')->where('id',$sub->id)->update(['upgrade_requested_at'=>now(),'updated_at'=>now()]);
                VenueInvitations::audit($request->user(),$venue,'subscription.upgrade_requested');
            }
        },3);
        return VenueContext::redirect('settings.subscription')->with('status','Upgrade requested. Pickle Lite will contact you to arrange payment.');
    }
}
