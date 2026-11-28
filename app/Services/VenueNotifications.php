<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

final class VenueNotifications
{
    public const CATEGORIES = ['booking','payment','open_play','account','trial','subscription','promotion'];

    public static function emit(Venue $venue,string $category,string $key,string $title,string $message,string $destination='calendar',bool $ownersOnly=false): void
    {
        $membership = $venue->members();
        if ($ownersOnly) $membership->wherePivot('role','owner');
        $members = $membership->pluck('users.id');
        if ($members->isEmpty()) return;
        $disabled = DB::table('notification_preferences')->whereIn('user_id',$members)->where('category',$category)->where('enabled',false)->pluck('user_id')->all();
        $rows=[];
        foreach ($members as $id) {
            // Trial and subscription notices are essential account information.
            if (!in_array($category,['trial','subscription'],true) && in_array((int)$id,array_map('intval',$disabled),true)) continue;
            $rows[]=['user_id'=>$id,'venue_id'=>$venue->id,'category'=>$category,'event_key'=>$venue->id.':'.$category.':'.$key,
                'title'=>$title,'message'=>$message,'destination'=>$destination,'created_at'=>now()];
        }
        if ($rows) DB::table('venue_notifications')->insertOrIgnore($rows);
    }

    public static function query(User $user)
    {
        return DB::table('venue_notifications')->where('user_id',$user->id)
            ->whereExists(function($q) use($user) {
                $q->selectRaw('1')->from('venue_memberships')->whereColumn('venue_memberships.venue_id','venue_notifications.venue_id')
                    ->where('venue_memberships.user_id',$user->id);
            });
    }

    public static function sweep(): void
    {
        DB::table('venue_subscriptions')->where('status','trial')->whereNotNull('trial_ends_at')
            ->where('trial_ends_at','<=',now()->addDays(config('selfservice.extension_window_days')))->orderBy('id')->chunkById(100,function($rows) {
                foreach($rows as $row) DB::transaction(function() use($row) {
                    $venue=Venue::whereKey($row->venue_id)->lockForUpdate()->first();
                    if (!$venue || !$venue->self_service) return;
                    $sub=TrialAccess::subscription($venue,true);
                    if (!$sub || $sub->status!=='trial' || !$sub->trial_ends_at) return;
                    $end=\Carbon\CarbonImmutable::parse($sub->trial_ends_at);
                    if ($end->lte(now())) {
                        DB::table('venue_subscriptions')->where('id',$sub->id)->update(['status'=>'trial_expired','updated_at'=>now()]);
                        self::emit($venue,'trial','expired-'.$sub->trial_extensions_count,'Free trial ended','New reservations are disabled. Existing bookings and payment links remain available.','subscription',true);
                    } elseif ($end->lte(now()->addDays(config('selfservice.extension_window_days')))) {
                        $message=TrialAccess::canExtend($sub) ? 'Your free trial is ending soon. You can extend it by 15 days or choose a paid plan.' : 'Your free trial is ending soon. Choose a paid plan to continue.';
                        self::emit($venue,'trial','ending-'.$sub->trial_extensions_count,'Free trial ending',$message,'subscription',true);
                    }
                },3);
            });
        // Limit hold expiry notices to recent activity.
        $since=now()->subDay();
        DB::table('bookings')->join('courts','courts.id','=','bookings.court_id')
            ->whereNull('bookings.paid_at')->whereNull('bookings.cancelled_at')
            ->whereBetween('bookings.payment_expires_at',[$since,now()])
            ->select('bookings.id','courts.venue_id')
            ->chunkById(100,function($rows) {
                foreach($rows as $row) {
                    $venue=Venue::find($row->venue_id);
                    if ($venue) self::emit($venue,'payment','booking-expired-'.$row->id,'Court payment hold expired','A court hold expired. Reconcile any late payment directly with the player.','calendar');
                }
            },'bookings.id','id');
        DB::table('open_play_registrations')->join('open_play_sessions','open_play_sessions.id','=','open_play_registrations.open_play_session_id')
            ->whereNull('open_play_registrations.paid_at')->whereNull('open_play_registrations.cancelled_at')
            ->whereBetween('open_play_registrations.payment_expires_at',[$since,now()])
            ->select('open_play_registrations.id','open_play_sessions.venue_id')
            ->chunkById(100,function($rows) {
                foreach($rows as $row) {
                    $venue=Venue::find($row->venue_id);
                    if ($venue) self::emit($venue,'payment','open-play-expired-'.$row->id,'Open Play payment hold expired','A player hold expired. Reconcile any late payment directly with the player.','open-play');
                }
            },'open_play_registrations.id','id');
        DB::table('venue_subscriptions')->where('status','active')->whereNotNull('renewal_date')
            ->whereBetween('renewal_date',[now('Asia/Manila')->toDateString(),now('Asia/Manila')->addDays(5)->toDateString()])
            ->orderBy('id')->chunkById(100,function($rows) {
                foreach($rows as $row) {
                    $venue=Venue::find($row->venue_id);
                    if ($venue) self::emit($venue,'subscription','renewal-'.$row->renewal_date,'Subscription renewal due','Your venue subscription renewal is approaching.','subscription',true);
                }
            });
    }
}
