<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PlatformRewards
{
    public const MILESTONES=['testimonial'=>1,'streak_100'=>1,'streak_300'=>2];
    public const LABELS=['testimonial'=>'Video testimonial','streak_100'=>'100-day streak','streak_300'=>'300-day streak'];
    public const TESTIMONIAL_DAYS=15;

    public static function isDemo(Venue $venue): bool
    {
        return filled(config('pickle.demo_slug')) && $venue->slug===config('pickle.demo_slug');
    }

    public static function visible(Venue $venue, ?object $sub): bool
    {
        return !self::isDemo($venue) && $sub && $venue->published &&
            ($venue->self_service || in_array($sub->status,['trial','active','trial_expired','overdue'],true));
    }

    public static function eligible(Venue $venue, ?object $sub): bool
    {
        return self::visible($venue,$sub) && $venue->status==='active' &&
            (($sub->status==='trial' && $sub->trial_ends_at && CarbonImmutable::parse($sub->trial_ends_at)->isFuture()) ||
            ($sub->status==='active' && $sub->renewal_date && $sub->renewal_date>now($venue->timezone)->toDateString()));
    }

    private static function owner(Venue $venue, User $actor): void
    {
        abort_unless($venue->members()->where('users.id',$actor->id)->wherePivot('role','owner')->exists(),403);
    }

    public static function current(?object $progress): int
    {
        if (!$progress) return 0;
        $today=now($progress->timezone)->toDateString();
        $yesterday=now($progress->timezone)->subDay()->toDateString();
        return in_array($progress->last_active_date,[$today,$yesterday],true) ? (int)$progress->current_streak : 0;
    }

    public static function summary(Venue $venue, ?object $sub=null): array
    {
        $sub ??= TrialAccess::subscription($venue);
        $progress=DB::table('venue_reward_progress')->where('venue_id',$venue->id)->first();
        $grants=DB::table('venue_reward_grants')->where('venue_id',$venue->id)->get()->keyBy('milestone');
        $zone=$progress->timezone ?? $venue->timezone;
        $today=now($zone)->toDateString();
        $streak=self::current($progress);
        $activeDays=(int)($progress->active_days ?? 0);
        $next=!$grants->has('streak_100')?100:(!$grants->has('streak_300')?300:null);
        $available=$grants->filter(fn($g)=>!$g->applied_at && !$g->revoked_at)->sum('months');
        $earned=$grants->filter(fn($g)=>!$g->revoked_at)->sum('months');
        $days=DB::table('venue_reward_days')->where('venue_id',$venue->id)->whereBetween('activity_date',[now($zone)->startOfWeek()->toDateString(),now($zone)->endOfWeek()->toDateString()])->pluck('activity_date')->all();
        return compact('sub','progress','grants','zone','today','streak','activeDays','next','available','earned','days')+
            ['today_done'=>$progress?->last_active_date===$today,'longest'=>(int)($progress->longest_streak ?? 0),
             'eligible'=>self::eligible($venue,$sub),'can_apply'=>self::canApply($venue,$sub)];
    }

    public static function record(Venue $venue, User $actor): void
    {
        if (!self::eligible($venue,TrialAccess::subscription($venue))) return;
        $quick=DB::table('venue_reward_progress')->where('venue_id',$venue->id)->first();
        if ($quick && $quick->last_active_date>=now($quick->timezone)->toDateString()) return;
        DB::transaction(function() use($venue,$actor) {
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            $sub=TrialAccess::subscription($venue,true);
            if (!self::eligible($venue,$sub) || !$venue->members()->where('users.id',$actor->id)->wherePivotIn('role',['owner','manager','staff'])->exists()) return;
            $old=DB::table('venue_reward_progress')->where('venue_id',$venue->id)->first();
            $zone=$old->timezone ?? $venue->timezone;
            $today=now($zone)->toDateString();
            if ($old && $old->last_active_date >= $today) return;
            $previous=now($zone)->subDay()->toDateString();
            $streak=$old && $old->last_active_date===$previous ? $old->current_streak+1 : 1;
            $days=(int)($old->active_days ?? 0)+1;
            DB::table('venue_reward_days')->insert(['venue_id'=>$venue->id,'activity_date'=>$today]);
            DB::table('venue_reward_progress')->updateOrInsert(['venue_id'=>$venue->id],[
                'timezone'=>$zone,'last_active_date'=>$today,'current_streak'=>$streak,
                'longest_streak'=>max($streak,(int)($old->longest_streak ?? 0)),'active_days'=>$days,
                'created_at'=>$old->created_at ?? now(),'updated_at'=>now(),
            ]);
            if ($days===self::TESTIMONIAL_DAYS) self::notify($venue,'testimonial-unlocked','Share your story','You can now submit a video testimonial to earn one free month.');
            if ($streak===100) self::grant($venue,$sub,'streak_100');
            if ($streak===300) self::grant($venue,$sub,'streak_300');
            if (in_array($streak,[25,75,99,200,299],true)) {
                $target=$streak<100?100:300;
                if (!DB::table('venue_reward_grants')->where('venue_id',$venue->id)->where('milestone','streak_'.$target)->exists()) {
                    self::notify($venue,'streak-'.$streak.'-'.$today,'Your '.$streak.'-day streak',($target-$streak).' more consecutive days until your next free subscription reward.');
                }
            }
        },3);
    }

    private static function notify(Venue $venue,string $key,string $title,string $message): void
    {
        VenueNotifications::emit($venue,'subscription','rewards-'.$key,$title,$message,'rewards',true);
    }

    // Every caller holds the venue row lock; uniqueness also protects the ledger.
    private static function grant(Venue $venue,object $sub,string $milestone): void
    {
        if (DB::table('venue_reward_grants')->where('venue_id',$venue->id)->where('milestone',$milestone)->exists()) return;
        DB::table('venue_reward_grants')->insert([
            'venue_id'=>$venue->id,'milestone'=>$milestone,'months'=>self::MILESTONES[$milestone],
            'monthly_value'=>$sub->monthly_fee,'earned_at'=>now(),
        ]);
        self::notify($venue,'earned-'.$milestone,'Reward earned',self::LABELS[$milestone].': '.self::MILESTONES[$milestone].' free subscription month(s) are available.');
    }

    public static function submit(Venue $venue,User $actor,string $reference,bool $consent): void
    {
        DB::transaction(function() use($venue,$actor,$reference,$consent) {
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            self::owner($venue,$actor);
            $sub=TrialAccess::subscription($venue,true);
            abort_unless(self::visible($venue,$sub),404);
            $progress=DB::table('venue_reward_progress')->where('venue_id',$venue->id)->first();
            $record=DB::table('venue_reward_testimonials')->where('venue_id',$venue->id)->first();
            if (($progress->active_days ?? 0)<self::TESTIMONIAL_DAYS || ($record && $record->status!=='rejected') ||
                DB::table('venue_reward_grants')->where('venue_id',$venue->id)->where('milestone','testimonial')->exists()) {
                throw ValidationException::withMessages(['testimonial'=>'This testimonial reward is not available for submission.']);
            }
            DB::table('venue_reward_testimonials')->updateOrInsert(['venue_id'=>$venue->id],[
                'submitted_by'=>$actor->id,'submission_reference'=>$reference,'marketing_consent'=>$consent,
                'status'=>'pending','reviewed_by'=>null,'review_note'=>null,'reviewed_at'=>null,
                'created_at'=>$record->created_at ?? now(),'updated_at'=>now(),
            ]);
            VenueInvitations::audit($actor,$venue,'rewards.testimonial_submitted',['marketing_consent'=>$consent]);
        },3);
    }

    public static function review(int $id,User $actor,bool $approved,string $note): void
    {
        abort_unless($actor->platform_admin,403);
        DB::transaction(function() use($id,$actor,$approved,$note) {
            $initial=DB::table('venue_reward_testimonials')->where('id',$id)->firstOrFail();
            $venue=Venue::whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();
            $sub=TrialAccess::subscription($venue,true);
            $record=DB::table('venue_reward_testimonials')->where('id',$id)->lockForUpdate()->firstOrFail();
            if ($record->status!=='pending' || !$sub || self::isDemo($venue)) throw ValidationException::withMessages(['testimonial'=>'Submission is no longer pending or eligible.']);
            DB::table('venue_reward_testimonials')->where('id',$id)->update([
                'status'=>$approved?'approved':'rejected','reviewed_by'=>$actor->id,'review_note'=>$note,'reviewed_at'=>now(),'updated_at'=>now(),
            ]);
            if ($approved) self::grant($venue,$sub,'testimonial');
            else self::notify($venue,'testimonial-revision-'.$id.'-'.now()->timestamp,'Testimonial needs review','Open Rewards to see the review note and resubmit.');
            VenueInvitations::audit($actor,$venue,'rewards.testimonial_reviewed',['id'=>$id,'approved'=>$approved]);
        },3);
    }

    public static function withdrawMarketing(Venue $venue,User $actor): void
    {
        DB::transaction(function() use($venue,$actor) {
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            self::owner($venue,$actor);
            DB::table('venue_reward_testimonials')->where('venue_id',$venue->id)->update(['marketing_consent'=>false,'updated_at'=>now()]);
            VenueInvitations::audit($actor,$venue,'rewards.marketing_consent_withdrawn');
        },3);
    }

    public static function canApply(Venue $venue,?object $sub): bool
    {
        if (!self::visible($venue,$sub) || $venue->status!=='active') return false;
        if ($sub->status==='active') return filled($sub->renewal_date);
        if (SubscriptionPlans::timed($sub) && $sub->status==='overdue' && $sub->renewal_date && $sub->renewal_date<=now($venue->timezone)->toDateString()) return true;
        return $venue->self_service && in_array($sub->status,['trial','trial_expired'],true) &&
            $sub->trial_ends_at && CarbonImmutable::parse($sub->trial_ends_at)->lte(now());
    }

    public static function apply(Venue $venue,User $actor,int $grantId): void
    {
        DB::transaction(function() use($venue,$actor,$grantId) {
            $venue=Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            self::owner($venue,$actor);
            $sub=TrialAccess::subscription($venue,true);
            $grant=DB::table('venue_reward_grants')->where('venue_id',$venue->id)->where('id',$grantId)->lockForUpdate()->firstOrFail();
            if ($grant->applied_at || $grant->revoked_at || !self::canApply($venue,$sub) ||
                ($venue->self_service && !$venue->members()->wherePivot('role','owner')->whereNotNull('email_verified_at')->exists())) {
                throw ValidationException::withMessages(['reward'=>'This credit cannot be applied right now. Review your subscription status.']);
            }
            $today=now($venue->timezone)->toDateString();
            $start=in_array($sub->status,['trial','trial_expired'],true) ? $today : max($today,$sub->renewal_date ?? $today);
            $new=CarbonImmutable::parse($start,$venue->timezone)->addMonthsNoOverflow((int)$grant->months)->toDateString();
            DB::table('venue_subscriptions')->where('id',$sub->id)->update([
                'status'=>'active','renewal_date'=>$new,'reward_managed'=>true,'updated_at'=>now(),
            ]);
            DB::table('venue_reward_grants')->where('id',$grant->id)->update([
                'applied_at'=>now(),'applied_by'=>$actor->id,'previous_status'=>$sub->status,'previous_renewal'=>$sub->renewal_date,'new_renewal'=>$new,
            ]);
            VenueInvitations::audit($actor,$venue,'rewards.applied',['grant_id'=>$grant->id,'months'=>$grant->months,'previous_renewal'=>$sub->renewal_date,'new_renewal'=>$new]);
            self::notify($venue,'applied-'.$grant->id,'Subscription reward applied','Your next subscription renewal is '.$new.'.');
        },3);
    }

    public static function revoke(int $id,User $actor,string $reason): void
    {
        abort_unless($actor->platform_admin,403);
        DB::transaction(function() use($id,$actor,$reason) {
            $initial=DB::table('venue_reward_grants')->where('id',$id)->firstOrFail();
            $venue=Venue::whereKey($initial->venue_id)->lockForUpdate()->firstOrFail();
            $grant=DB::table('venue_reward_grants')->where('id',$id)->lockForUpdate()->firstOrFail();
            if ($grant->applied_at || $grant->revoked_at) throw ValidationException::withMessages(['reward'=>'Only an unused credit can be revoked.']);
            DB::table('venue_reward_grants')->where('id',$id)->update(['revoked_at'=>now(),'revoked_by'=>$actor->id,'revoke_reason'=>$reason]);
            VenueInvitations::audit($actor,$venue,'rewards.revoked',['grant_id'=>$id]);
            self::notify($venue,'revoked-'.$id,'Reward credit reviewed','An unused credit was revoked. Review your Rewards page for the reason.');
        },3);
    }

    public static function sweep(): void
    {
        // Expire only subscriptions that have used the reward-managed billing path.
        DB::table('venue_subscriptions')->where(fn($q)=>$q->where('reward_managed',true)->orWhere('qr_managed',true)->orWhereIn('billing_plan',['yearly','yearly_promo']))->where('status','active')
            ->where('renewal_date','<=',now()->addDay()->toDateString())->orderBy('id')->chunkById(100,function($rows) {
                foreach($rows as $row) DB::transaction(function() use($row) {
                    $venue=Venue::whereKey($row->venue_id)->lockForUpdate()->first();
                    if (!$venue) return;
                    $sub=TrialAccess::subscription($venue,true);
                    if (!$sub || !SubscriptionPlans::timed($sub) || $sub->status!=='active' || !$sub->renewal_date || $sub->renewal_date>now($venue->timezone)->toDateString()) return;
                    DB::table('venue_subscriptions')->where('id',$sub->id)->update(['status'=>'overdue','updated_at'=>now()]);
                    self::notify($venue,'renewal-expired-'.$sub->renewal_date,'Subscription renewal due','Your subscription period has ended. Renew your subscription or apply an available reward.');
                },3);
            });
        DB::table('venue_reward_progress')->where('last_active_date','>=',now()->subDays(2)->toDateString())
            ->orderBy('venue_id')->chunkById(100,function($rows) {
                foreach($rows as $row) {
                    $local=now($row->timezone);
                    if ($local->hour<18 || $row->last_active_date!==$local->copy()->subDay()->toDateString()) continue;
                    $venue=Venue::find($row->venue_id);
                    if (!$venue || !self::eligible($venue,TrialAccess::subscription($venue))) continue;
                    if (DB::table('venue_reward_progress')->where('venue_id',$venue->id)->value('last_active_date')!==$row->last_active_date) continue;
                    if (DB::table('venue_reward_grants')->where('venue_id',$venue->id)->where('milestone','streak_300')->exists()) continue;
                    self::notify($venue,'risk-'.$local->toDateString(),'Keep your streak alive','Use your venue workspace today to maintain your streak before midnight.');
                }
            },'venue_id');
        // Aggregate progress and the financial ledger are separate from daily report rows.
        DB::table('venue_reward_days')->where('activity_date','<',now()->subYear()->toDateString())->delete();
    }
}
