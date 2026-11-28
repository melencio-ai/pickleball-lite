<?php

namespace App\Services;

use App\Models\Venue;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final class SubscriptionPlans
{
    public const MONTHLY=100000;
    public const YEARLY=1000000;
    public const INTRO=500000;
    public const LABELS=['monthly'=>'Monthly','yearly'=>'Yearly','yearly_promo'=>'First-year special'];

    public static function annual(?object $sub): bool
    {
        return in_array($sub?->billing_plan ?? 'monthly',['yearly','yearly_promo'],true);
    }

    public static function offerOwner(Venue $venue, ?object $sub): ?User
    {
        if (!$venue->self_service || !$sub || !in_array($sub->status,['trial','trial_expired','active','overdue'],true) ||
            ($sub->promo_redeemed_at ?? null) || DB::table('subscription_payments')->where('venue_subscription_id',$sub->id)
                ->whereIn('kind',['monthly','yearly','yearly_promo'])->exists()) return null;
        // One offer per original, verified self-service account and its original venue.
        return $venue->members()->wherePivot('role','owner')->where('users.venue_id',$venue->id)
            ->where('users.self_registered',true)->whereNotNull('users.email_verified_at')
            ->where('users.created_at','<=',now()->subDays(14))->orderBy('users.id')->first();
    }

    public static function eligible(Venue $venue,?object $sub,?User $user): bool
    {
        return $user && self::offerOwner($venue,$sub)?->id===$user->id;
    }

    public static function sweep(): void
    {
        // The Railway cron runs platform:notifications directly, not schedule:run.
        // Expire paid annual terms in that same worker so status and access agree.
        DB::table('venue_subscriptions')->whereIn('billing_plan',['yearly','yearly_promo'])->where('status','active')
            ->whereNotNull('renewal_date')->where('renewal_date','<=',now()->addDay()->toDateString())->orderBy('id')
            ->chunkById(100,function($rows) {
                foreach($rows as $row) DB::transaction(function() use($row) {
                    $venue=Venue::whereKey($row->venue_id)->lockForUpdate()->first();
                    if(!$venue)return;
                    $sub=TrialAccess::subscription($venue,true);
                    if(!self::annual($sub) || $sub->status!=='active' || !$sub->renewal_date || $sub->renewal_date>now($venue->timezone)->toDateString())return;
                    DB::table('venue_subscriptions')->where('id',$sub->id)->update(['status'=>'overdue','updated_at'=>now()]);
                    VenueNotifications::emit($venue,'subscription','annual-expired-'.$sub->renewal_date,'Yearly subscription ended','Renew your yearly subscription or choose a monthly plan. No automatic charges apply.','subscription',true);
                },3);
            });
        DB::table('venue_subscriptions')->whereNull('promo_redeemed_at')->whereIn('status',['trial','trial_expired','active','overdue'])
            ->whereExists(fn($q)=>$q->selectRaw('1')->from('users')->whereColumn('users.venue_id','venue_subscriptions.venue_id')
                ->where('users.self_registered',true)->whereNotNull('users.email_verified_at')->where('users.created_at','<=',now()->subDays(14)))
            ->orderBy('id')->chunkById(100,function($rows) {
                foreach($rows as $row) {
                    $delivery=DB::transaction(function() use($row) {
                        $venue=Venue::whereKey($row->venue_id)->lockForUpdate()->first();
                        if(!$venue)return null;
                        $sub=TrialAccess::subscription($venue,true);
                        $owner=self::offerOwner($venue,$sub);
                        if(!$owner || DB::table('notification_preferences')->where('user_id',$owner->id)->where('category','promotion')->where('enabled',false)->exists())return null;
                        DB::table('venue_notifications')->insertOrIgnore([
                            'user_id'=>$owner->id,'venue_id'=>$venue->id,'category'=>'promotion',
                            'event_key'=>$venue->id.':promotion:first-year','title'=>'Your special yearly offer',
                            'message'=>'Get your first year for ₱5,000 instead of ₱10,000. Open your subscription to claim this one-time offer.',
                            'destination'=>'subscription','created_at'=>now(),
                        ]);
                        if($sub->promo_email_attempted_at || !PlatformEmail::ready())return null;
                        // Reserve delivery before SMTP. Never automatically resend an ambiguous SMTP attempt.
                        DB::table('venue_subscriptions')->where('id',$sub->id)->update(['promo_email_attempted_at'=>now(),'updated_at'=>now()]);
                        return [$venue,$owner,$sub->id];
                    },3);
                    if(!$delivery)continue;
                    [$venue,$owner,$id]=$delivery;
                    $url=route('app.settings.subscription',['venueSlug'=>$venue->slug]);
                    $preferences=route('app.notifications.index');
                    if(PlatformEmail::send('yearly_promotion',$owner->email,'Your special Pickle Lite yearly offer',
                        "Get 50% off your first year of Pickle Lite.\n\nRegular yearly price: PHP 10,000\nYour first year: PHP 5,000\nFuture yearly renewals: PHP 10,000. No automatic charges.\n\nClaim your offer: ".$url."\n\nTo stop optional offers, update your notification preferences: ".$preferences)) {
                        DB::table('venue_subscriptions')->where('id',$id)->update(['promo_email_sent_at'=>now(),'updated_at'=>now()]);
                    }
                }
            });
    }

    public static function verifiedPayment(Venue $venue,object $sub,string $kind,int $amount): array
    {
        if($kind==='monthly') {
            if(self::annual($sub) && $sub->status==='active' && $sub->renewal_date && $sub->renewal_date>now($venue->timezone)->toDateString()) {
                throw ValidationException::withMessages(['kind'=>'The yearly term is still active. Record monthly payments after the term expires.']);
            }
            return ['billing_plan'=>'monthly','requested_plan'=>null,'upgrade_requested_at'=>null];
        }
        $expected=$kind==='yearly_promo'?self::INTRO:self::YEARLY;
        if($amount!==$expected)throw ValidationException::withMessages(['amount'=>'Record the exact verified yearly plan price.']);
        if($kind==='yearly_promo' && (!self::offerOwner($venue,$sub) || $sub->requested_plan!=='yearly_promo'))
            throw ValidationException::withMessages(['kind'=>'The first-year offer is not eligible or has not been claimed.']);
        if(in_array($sub->status,['cancelled','suspended'],true) ||
            ($venue->self_service && !$venue->members()->wherePivot('role','owner')->whereNotNull('users.email_verified_at')->exists()))
            throw ValidationException::withMessages(['kind'=>'Resolve account status and owner verification before yearly activation.']);
        $today=now($venue->timezone)->toDateString();
        $start=$today;
        if($sub->status==='active' && $sub->renewal_date)$start=max($start,$sub->renewal_date);
        if($sub->status==='trial' && $sub->trial_ends_at)$start=max($start,CarbonImmutable::parse($sub->trial_ends_at)->setTimezone($venue->timezone)->toDateString());
        $update=['status'=>'active','billing_plan'=>$kind,'renewal_date'=>CarbonImmutable::parse($start,$venue->timezone)->addYearNoOverflow()->toDateString(),
            'requested_plan'=>null,'upgrade_requested_at'=>null];
        if($kind==='yearly_promo')$update['promo_redeemed_at']=now();
        return $update;
    }
}
