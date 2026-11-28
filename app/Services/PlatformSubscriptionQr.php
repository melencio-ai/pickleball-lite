<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformSubscriptionQr
{
    public const OPEN = ['awaiting_payment','submitted'];
    public const AMOUNTS = ['monthly'=>SubscriptionPlans::MONTHLY,'yearly'=>SubscriptionPlans::YEARLY,'yearly_promo'=>SubscriptionPlans::INTRO];

    public static function settings(bool $lock = false): object
    {
        $query=DB::table('platform_subscription_qr_settings')->where('id',1);
        if ($lock) $query->lockForUpdate();
        return $query->firstOrFail();
    }

    public static function base(object $settings): ?string
    {
        return $settings->base_ciphertext ? Crypt::decryptString($settings->base_ciphertext) : config('pickle.subscription_qr_base');
    }

    public static function enabled(): bool
    {
        $settings=self::settings();
        return (bool)$settings->enabled && filled(self::base($settings));
    }

    public static function pending(?object $sub, bool $lock=false): ?object
    {
        if (!$sub) return null;
        $query=DB::table('subscription_payment_requests')->where('venue_subscription_id',$sub->id)->whereIn('status',self::OPEN);
        if ($lock) $query->lockForUpdate();
        return $query->latest('id')->first();
    }

    private static function fail(string $key,string $message): never
    {
        throw ValidationException::withMessages([$key=>$message]);
    }

    private static function owner(Venue $venue,User $actor): void
    {
        abort_unless($venue->members()->where('users.id',$actor->id)->wherePivot('role','owner')->exists(),403);
    }

    private static function validatePlan(Venue $venue,object $sub,string $plan): void
    {
        if (!isset(self::AMOUNTS[$plan]) || in_array($venue->status,['suspended','cancelled'],true) ||
            !in_array($sub->status,['trial','trial_expired','active','overdue'],true))
            self::fail('plan','This subscription is not available for payment. Contact Pickle Lite.');
        if ($venue->self_service && (!$venue->members()->wherePivot('role','owner')->whereNotNull('email_verified_at')->exists() ||
            !DB::table('venue_onboarding')->where('venue_id',$venue->id)->whereNotNull('completed_at')->exists()))
            self::fail('plan','Complete onboarding and email verification before paying.');
        if ($plan==='monthly' && SubscriptionPlans::annual($sub) && $sub->status==='active' &&
            $sub->renewal_date && $sub->renewal_date>now($venue->timezone)->toDateString())
            self::fail('plan','Your yearly term is still active. Choose yearly or wait until it expires.');
    }

    public static function create(Venue $venue,User $actor,string $plan): void
    {
        DB::transaction(function () use ($venue,$actor,$plan) {
            // Global settings lock also serializes payment verification and manual ledger writes.
            $settings=self::settings(true);
            if (!$settings->enabled || !filled($base=self::base($settings))) self::fail('plan','QR payments are not available yet.');
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            self::owner($venue,$actor);
            $sub=TrialAccess::subscription($venue,true);
            abort_unless($sub,404);
            if ($pending=self::pending($sub,true)) {
                if ($pending->plan===$plan) return;
                self::fail('plan','You already have an open payment. Resolve it before choosing another plan.');
            }
            self::validatePlan($venue,$sub,$plan);
            if ($plan==='yearly_promo' && !SubscriptionPlans::eligible($venue,$sub,$actor))
                self::fail('plan','The first-year offer is not available for your account.');
            try {
                $qr=app(EmvPaymentQr::class)->generate($base,self::AMOUNTS[$plan]);
            } catch (\InvalidArgumentException $e) {
                self::fail('plan','The payment QR is unavailable. Contact Pickle Lite.');
            }
            $reference='PL-'.strtoupper((string)Str::ulid());
            DB::table('subscription_payment_requests')->insert([
                'venue_subscription_id'=>$sub->id,'requested_by'=>$actor->id,'reference'=>$reference,'plan'=>$plan,
                'amount'=>self::AMOUNTS[$plan],'qr_ciphertext'=>Crypt::encryptString($qr),
                'status'=>'awaiting_payment','created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('venue_subscriptions')->where('id',$sub->id)->update([
                'requested_plan'=>$plan,'upgrade_requested_at'=>now(),'updated_at'=>now(),
            ]);
            VenueInvitations::audit($actor,$venue,'subscription.qr_requested',['reference'=>$reference,'plan'=>$plan]);
        },3);
    }

    public static function ownerAction(Venue $venue,User $actor,int $id,string $action): void
    {
        DB::transaction(function () use ($venue,$actor,$id,$action) {
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            self::owner($venue,$actor);
            $sub=TrialAccess::subscription($venue,true);
            abort_unless($sub,404);
            $row=DB::table('subscription_payment_requests')->where('venue_subscription_id',$sub->id)->where('id',$id)->lockForUpdate()->firstOrFail();
            if ($action==='submit' && $row->status==='submitted') return;
            if ($row->status!=='awaiting_payment') self::fail('payment','This payment request has already been submitted or closed.');
            $changes=$action==='submit'
                ? ['status'=>'submitted','submitted_at'=>now()]
                : ['status'=>'cancelled'];
            DB::table('subscription_payment_requests')->where('id',$id)->update($changes+['updated_at'=>now()]);
            if ($action==='cancel') DB::table('venue_subscriptions')->where('id',$sub->id)->update(['requested_plan'=>null,'upgrade_requested_at'=>null,'updated_at'=>now()]);
            VenueInvitations::audit($actor,$venue,'subscription.qr_'.$changes['status'],['reference'=>$row->reference]);
        },3);
    }

    public static function review(User $actor,int $id,array $data): void
    {
        abort_unless($actor->platform_admin,403);
        DB::transaction(function () use ($actor,$id,$data) {
            self::settings(true);
            $initial=DB::table('subscription_payment_requests')->join('venue_subscriptions','venue_subscriptions.id','=','subscription_payment_requests.venue_subscription_id')
                ->where('subscription_payment_requests.id',$id)->select('venue_subscriptions.venue_id')->firstOrFail();
            $venue=Venue::whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();
            $sub=TrialAccess::subscription($venue,true);
            $row=DB::table('subscription_payment_requests')->where('id',$id)->where('venue_subscription_id',$sub->id)->lockForUpdate()->firstOrFail();
            if (!in_array($row->status,self::OPEN,true)) self::fail('payment','This payment request is already closed.');
            $changes=['reviewed_at'=>now(),'reviewed_by'=>$actor->id,'updated_at'=>now()];
            if ($data['decision']==='reject') {
                DB::table('subscription_payment_requests')->where('id',$id)->update($changes+['status'=>'rejected','review_note'=>$data['reason']]);
                DB::table('venue_subscriptions')->where('id',$sub->id)->update(['requested_plan'=>null,'upgrade_requested_at'=>null,'updated_at'=>now()]);
                VenueInvitations::audit($actor,$venue,'subscription.qr_rejected',['reference'=>$row->reference]);
                VenueNotifications::emit($venue,'subscription','qr-rejected-'.$id,'Subscription payment needs attention',$data['reason'],'subscription',true);
                return;
            }
            self::validatePlan($venue,$sub,$row->plan);
            $amount=Pricing::centavos($data['amount']);
            if ($amount!==$row->amount || $amount!==self::AMOUNTS[$row->plan])
                self::fail('amount','Confirm the exact amount received for this payment.');
            $ref=strtoupper(trim($data['bank_reference']));
            if (!$ref || DB::table('subscription_payments')->where('reference',$ref)->exists())
                self::fail('bank_reference','This bank reference is empty or already recorded.');
            // Keep the normal annual and promo validation rules. Monthly QR payments
            // explicitly create a paid term, unlike legacy manual payment recording.
            $term=SubscriptionPlans::verifiedPayment($venue,$sub,$row->plan,$amount);
            if ($row->plan==='monthly') {
                $start=now($venue->timezone)->toDateString();
                if ($sub->status==='active' && $sub->renewal_date) $start=max($start,$sub->renewal_date);
                if ($sub->status==='trial' && $sub->trial_ends_at) {
                    $end=CarbonImmutable::parse($sub->trial_ends_at)->setTimezone($venue->timezone);
                    $start=max($start,$end->toDateString());
                }
                $term+=['status'=>'active','renewal_date'=>CarbonImmutable::parse($start,$venue->timezone)->addMonthNoOverflow()->toDateString()];
            }
            $payment=DB::table('subscription_payments')->insertGetId([
                'venue_subscription_id'=>$sub->id,'kind'=>$row->plan,'amount'=>$amount,'reference'=>$ref,
                'received_on'=>$data['received_on'],'recorded_by'=>$actor->id,'created_at'=>now(),'updated_at'=>now(),
            ]);
            DB::table('venue_subscriptions')->where('id',$sub->id)->update($term+['qr_managed'=>true,'updated_at'=>now()]);
            DB::table('subscription_payment_requests')->where('id',$id)->update($changes+['status'=>'approved','subscription_payment_id'=>$payment]);
            VenueInvitations::audit($actor,$venue,'subscription.qr_approved',['reference'=>$row->reference,'payment_id'=>$payment,'amount'=>$amount,'plan'=>$row->plan]);
            VenueNotifications::emit($venue,'subscription','qr-approved-'.$id,'Subscription payment verified','Your subscription is active until '.$term['renewal_date'].'.','subscription',true);
        },3);
    }
}
