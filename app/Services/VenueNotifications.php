<?php

namespace App\Services;

use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\DB;

final class VenueNotifications
{
    public const CATEGORIES = ['booking','payment','open_play','account','trial','subscription'];

    public static function emit(Venue $venue,string $category,string $key,string $title,string $message,string $destination='calendar',bool $ownersOnly=false): void
    {
        $members = $venue->members()->when($ownersOnly,fn($q)=>$q->wherePivot('role','owner'))->pluck('users.id');
        if ($members->isEmpty()) return;
        $disabled = DB::table('notification_preferences')->whereIn('user_id',$members)->where('category',$category)->where('enabled',false)->pluck('user_id')->all();
        $rows=[];
        foreach ($members as $id) {
            // Trial and subscription notices are essential account information.
            if (!in_array($category,['trial','subscription'],true) && in_array($id,$disabled)) continue;
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
            ->where('trial_ends_at','<=',now()->addDays(5))->orderBy('id')->chunkById(100,function($rows) {
                foreach($rows as $row) DB::transaction(function() use($row) {
                    $venue=Venue::whereKey($row->venue_id)->lockForUpdate()->first();
                    if (!$venue || !$venue->self_service) return;
                    $sub=TrialAccess::subscription($venue,true);
                    if (!$sub || $sub->status!=='trial' || !$sub->trial_ends_at) return;
                    $end=\Carbon\CarbonImmutable::parse($sub->trial_ends_at);
                    if ($end->lte(now())) {
                        DB::table('venue_subscriptions')->where('id',$sub->id)->update(['status'=>'trial_expired','updated_at'=>now()]);
                        self::emit($venue,'trial','expired-'.$sub->trial_extensions_count,'Free trial ended','New reservations are disabled. Existing bookings and payment links remain available.','subscription',true);
                    } elseif ($end->lte(now()->addDays(5))) {
                        $remaining=max(1,(int)ceil(now()->diffInSeconds($end)/86400));
                        self::emit($venue,'trial','ending-'.$sub->trial_extensions_count,'Free trial ending','Your free trial is ending soon. Review your extension or upgrade options.','subscription',true);
                    }
                },3);
            });
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
