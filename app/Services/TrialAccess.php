<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class TrialAccess
{
    public static function subscription(Venue $venue, bool $lock = false, bool $shared = false): ?object
    {
        $query = DB::table('venue_subscriptions')->where('venue_id',$venue->id);
        if ($lock) $query->lockForUpdate();
        elseif ($shared) $query->sharedLock();
        return $query->first();
    }

    public static function allowed(Venue $venue): bool
    {
        if (!$venue->self_service) {
            // A nonlocking read inside an InnoDB booking transaction can establish
            // a stale consistent snapshot before the court/session lock is acquired.
            $legacy=self::subscription($venue, shared:DB::transactionLevel()>0);
            if (!$legacy?->reward_managed && !SubscriptionPlans::annual($legacy)) return true;
            return $legacy->status==='active' && $legacy->renewal_date && $legacy->renewal_date>now($venue->timezone)->toDateString();
        }
        $sub = self::subscription($venue, shared:DB::transactionLevel()>0);
        return $sub && (($sub->status === 'active' && ((!$sub->reward_managed && !SubscriptionPlans::annual($sub)) ||
            ($sub->renewal_date && $sub->renewal_date>now($venue->timezone)->toDateString()))) ||
            ($sub->status === 'trial' && $sub->trial_ends_at && CarbonImmutable::parse($sub->trial_ends_at)->isFuture()));
    }

    public static function ensure(Venue $venue): void
    {
        if (!self::allowed($venue) || ($venue->self_service && ($venue->status !== 'active' || !$venue->published))) throw ValidationException::withMessages(['subscription'=>'Your trial or subscription is inactive. Existing bookings can still be managed.']);
    }

    public static function canExtend(?object $sub): bool
    {
        if (!$sub || $sub->status !== 'trial' || !$sub->trial_ends_at || !$sub->trial_started_at ||
            $sub->trial_extensions_count >= config('selfservice.max_extensions')) return false;
        $end = CarbonImmutable::parse($sub->trial_ends_at);
        return $end->isFuture() && $end->lte(now()->addDays(config('selfservice.extension_window_days')));
    }

    public static function start(Venue $venue): void
    {
        $sub = self::subscription($venue,true);
        if (!$sub || $sub->status !== 'onboarding' || $sub->trial_started_at) return;
        $now = now();
        DB::table('venue_subscriptions')->where('id',$sub->id)->update([
            'status'=>'trial','trial_started_at'=>$now,'trial_ends_at'=>$now->copy()->addDays(config('selfservice.trial_days')),
            'updated_at'=>$now,
        ]);
    }

    public static function extend(Venue $venue, User $actor, int $expectedCount): void
    {
        DB::transaction(function () use ($venue,$actor,$expectedCount) {
            $venue = Venue::whereKey($venue->id)->lockForUpdate()->firstOrFail();
            abort_unless($venue->self_service && $venue->members()->where('users.id',$actor->id)->wherePivot('role','owner')->exists(),403);
            $sub = self::subscription($venue,true);
            if (!self::canExtend($sub) || (int)$sub->trial_extensions_count !== $expectedCount) {
                throw ValidationException::withMessages(['trial'=>'An extension is not available right now. Refresh your subscription.']);
            }
            $old = CarbonImmutable::parse($sub->trial_ends_at);
            $new = $old->addDays(config('selfservice.extension_days'));
            $maximum = CarbonImmutable::parse($sub->trial_started_at)->addDays(config('selfservice.trial_days') + config('selfservice.max_extensions') * config('selfservice.extension_days'));
            if ($new->gt($maximum)) throw ValidationException::withMessages(['trial'=>'The maximum trial duration has been reached.']);
            $sequence = $expectedCount+1;
            DB::table('trial_extensions')->insert([
                'venue_subscription_id'=>$sub->id,'sequence'=>$sequence,'previous_end'=>$old,'new_end'=>$new,
                'requested_by'=>$actor->id,'created_at'=>now(),
            ]);
            DB::table('venue_subscriptions')->where('id',$sub->id)->update(['trial_extensions_count'=>$sequence,'trial_ends_at'=>$new,'updated_at'=>now()]);
            VenueInvitations::audit($actor,$venue,'trial.extended',['sequence'=>$sequence,'ends_at'=>$new->toIso8601String()]);
            VenueNotifications::emit($venue,'trial','extended-'.$sequence,'Trial extended','Your free trial has been extended by 15 days.','subscription',true);
        },3);
    }
}
