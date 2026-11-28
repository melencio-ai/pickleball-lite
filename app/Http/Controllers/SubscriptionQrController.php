<?php

namespace App\Http\Controllers;

use App\Services\EmvPaymentQr;
use App\Services\PlatformSubscriptionQr;
use App\Services\Pricing;
use App\Services\VenueContext;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionQrController extends Controller
{
    public function checkout(Request $request)
    {
        $data=$request->validate(['plan'=>'required|in:monthly,yearly,yearly_promo']);
        PlatformSubscriptionQr::create(VenueContext::venue(),$request->user(),$data['plan']);
        return VenueContext::redirect('settings.subscription')->with('status','Payment QR ready. Pay and send your receipt in Messenger.');
    }

    public function ownerAction(Request $request,int $payment,string $action)
    {
        if ($action==='cancel') $request->validate(['not_paid'=>'accepted']);
        PlatformSubscriptionQr::ownerAction(VenueContext::venue(),$request->user(),$payment,$action);
        return VenueContext::redirect('settings.subscription')->with('status',$action==='submit'?'Receipt submission recorded. Awaiting verification.':'Unpaid request cancelled.');
    }

    public function index(Request $request)
    {
        $filter=$request->validate(['status'=>'nullable|in:pending,approved,rejected,cancelled,all'])['status'] ?? 'pending';
        $settings=PlatformSubscriptionQr::settings();
        $base=PlatformSubscriptionQr::base($settings);
        $preview=null;
        if ($base) {
            try { $preview=app(EmvPaymentQr::class)->generate($base,100000); }
            catch (\InvalidArgumentException $e) { /* Invalid configuration must not expose a checkout. */ }
        }
        $query=DB::table('subscription_payment_requests as r')
            ->join('venue_subscriptions as s','s.id','=','r.venue_subscription_id')
            ->join('venues as v','v.id','=','s.venue_id')->join('users as u','u.id','=','r.requested_by')
            ->leftJoin('subscription_payments as p','p.id','=','r.subscription_payment_id')
            ->select('r.id','r.reference','r.plan','r.amount','r.status','r.created_at','r.submitted_at','r.review_note','r.reviewed_at','v.name as venue_name','v.id as venue_id','u.name as owner_name','p.reference as bank_reference');
        if ($filter==='pending') $query->whereIn('r.status',PlatformSubscriptionQr::OPEN);
        elseif ($filter!=='all') $query->where('r.status',$filter);
        $payments=$query->orderByDesc('r.id')->paginate(20)->withQueryString();
        return response()->view('platform.subscription-payments',compact('settings','preview','payments','filter'))->header('Cache-Control','private, no-store');
    }

    public function settings(Request $request)
    {
        $data=$request->validate(['base'=>'nullable|string|max:512','enabled'=>'sometimes|boolean','provider_confirmed'=>'sometimes|accepted']);
        $enabled=$request->boolean('enabled');
        DB::transaction(function () use ($request,$data,$enabled) {
            $settings=PlatformSubscriptionQr::settings(true);
            $replacement=trim($data['base'] ?? '');
            $base=$replacement!=='' ? $replacement : PlatformSubscriptionQr::base($settings);
            if ($enabled && (!$base || !$request->boolean('provider_confirmed')))
                throw ValidationException::withMessages(['provider_confirmed'=>'Confirm provider support for reusable, amount-adjusted QR codes before enabling payments.']);
            if ($base && ($enabled || $replacement!=='')) {
                try {
                    foreach (PlatformSubscriptionQr::AMOUNTS as $amount) app(EmvPaymentQr::class)->generate($base,$amount);
                } catch (\InvalidArgumentException $e) {
                    throw ValidationException::withMessages(['base'=>'Enter a valid Philippine peso EMV QR code with a matching checksum.']);
                }
            }
            $changes=['enabled'=>$enabled,'updated_at'=>now()];
            if ($replacement!=='') $changes['base_ciphertext']=Crypt::encryptString($replacement);
            DB::table('platform_subscription_qr_settings')->where('id',1)->update($changes);
            VenueInvitations::audit($request->user(),null,'subscription.qr_settings',['enabled'=>$enabled,'base_replaced'=>$replacement!=='']);
        },3);
        return back()->with('status','Platform subscription QR settings saved.');
    }

    public function review(Request $request,int $payment)
    {
        $decision=$request->validate(['decision'=>'required|in:approve,reject'])['decision'];
        $data=$decision==='approve' ? $request->validate([
            'amount'=>['required','regex:'.Pricing::PESOS],
            'bank_reference'=>'required|string|max:100','received_on'=>'required|date_format:Y-m-d|before_or_equal:today',
            'verified_in_messenger'=>'accepted',
        ]) : $request->validate(['reason'=>'required|string|max:500']);
        PlatformSubscriptionQr::review($request->user(),$payment,$data+['decision'=>$decision]);
        return back()->with('status',$decision==='approve'?'Payment approved and subscription extended.':'Payment request rejected.');
    }
}
