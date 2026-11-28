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
        $promo=\App\Services\SubscriptionPlans::eligible($venue,$subscription,auth()->user());
        $qrEnabled=\App\Services\PlatformSubscriptionQr::enabled();
        $pending=\App\Services\PlatformSubscriptionQr::pending($subscription);
        $qr=$pending && $pending->status==='awaiting_payment' ? \Illuminate\Support\Facades\Crypt::decryptString($pending->qr_ciphertext) : null;
        $payments=$subscription ? DB::table('subscription_payments')->where('venue_subscription_id',$subscription->id)->orderByDesc('received_on')->orderByDesc('id')->paginate(15) : collect();
        $lastRequest=$subscription ? DB::table('subscription_payment_requests')->where('venue_subscription_id',$subscription->id)->latest('id')->first() : null;
        return response()->view('platform.subscription',compact('venue','subscription','canExtend','promo','qrEnabled','pending','qr','payments','lastRequest'))->header('Cache-Control','private, no-store');
    }

    public function extend(Request $request)
    {
        $data=$request->validate(['extension_count'=>'required|integer|between:0,2']);
        TrialAccess::extend(VenueContext::venue(),$request->user(),(int)$data['extension_count']);
        return VenueContext::redirect('settings.subscription')->with('status','Your trial was extended by 15 days.');
    }

    public function upgrade(Request $request)
    {
        $plan=$request->validate(['plan'=>'sometimes|in:monthly,yearly,yearly_promo'])['plan'] ?? 'monthly';
        DB::transaction(function() use($request,$plan) {
            $venue=Venue::whereKey(VenueContext::venue()->id)->lockForUpdate()->firstOrFail();
            $sub=TrialAccess::subscription($venue,true);
            abort_unless($sub,404);
            if (\App\Services\PlatformSubscriptionQr::pending($sub,true)) {
                throw \Illuminate\Validation\ValidationException::withMessages(['plan'=>'Resolve the existing QR payment before changing plans.']);
            }
            if(in_array($sub->status,['suspended','cancelled'],true) ||
                ($plan==='yearly_promo' && !\App\Services\SubscriptionPlans::eligible($venue,$sub,$request->user()))) {
                throw \Illuminate\Validation\ValidationException::withMessages(['plan'=>'This plan is not available for your account.']);
            }
            if(!$sub->upgrade_requested_at || $sub->requested_plan!==$plan) {
                DB::table('venue_subscriptions')->where('id',$sub->id)->update(['upgrade_requested_at'=>now(),'requested_plan'=>$plan,'updated_at'=>now()]);
                VenueInvitations::audit($request->user(),$venue,'subscription.upgrade_requested',['plan'=>$plan]);
            }
        },3);
        return VenueContext::redirect('settings.subscription')->with('status','Plan requested. Message Pickle Lite to arrange payment.');
    }
}
