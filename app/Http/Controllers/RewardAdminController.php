<?php

namespace App\Http\Controllers;

use App\Services\PlatformRewards;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RewardAdminController extends Controller
{
    public function index(Request $request)
    {
        $data=$request->validate(['tab'=>'nullable|in:overview,streaks,testimonials,ledger','days'=>'nullable|in:7,30,90','q'=>'nullable|string|max:100']);
        $tab=$data['tab'] ?? 'overview';$range=(int)($data['days'] ?? 30);$search=$data['q'] ?? '';
        $today=CarbonImmutable::today('Asia/Manila');
        $start=$today->subDays($range-1)->toDateString();
        $dates=DB::table('venue_reward_days')->whereBetween('activity_date',[$start,$today->toDateString()]);
        $issued=DB::table('venue_reward_grants')->whereNull('revoked_at');
        $rangeIssued=(clone $issued)->where('earned_at','>=',$today->subDays($range-1)->utc());
        $activeStreaks=0;
        foreach(DB::table('venue_reward_progress')->select('timezone','last_active_date')->selectRaw('count(*) as total')->groupBy('timezone','last_active_date')->get() as $group) {
            if (in_array($group->last_active_date,[now($group->timezone)->toDateString(),now($group->timezone)->subDay()->toDateString()],true)) $activeStreaks+=(int)$group->total;
        }
        $stats=[
            'active_venues'=>(clone $dates)->distinct()->count('venue_id'),
            'active_streaks'=>$activeStreaks,
            'pending'=>DB::table('venue_reward_testimonials')->where('status','pending')->count(),
            'testimonial'=>DB::table('venue_reward_testimonials')->where('status','approved')->count(),
            'hundred'=>(clone $issued)->where('milestone','streak_100')->count(),
            'three_hundred'=>(clone $issued)->where('milestone','streak_300')->count(),
            'issued_months'=>(clone $rangeIssued)->sum('months'),
            'issued_value'=>(int)(clone $rangeIssued)->selectRaw('coalesce(sum(months * monthly_value),0) as value')->first()->value,
            'available_months'=>(clone $issued)->whereNull('applied_at')->sum('months'),
            'applied_months'=>(clone $issued)->where('applied_at','>=',$today->subDays($range-1)->utc())->sum('months'),
        ];
        $daily=(clone $dates)->select('activity_date')->selectRaw('count(*) as total')->groupBy('activity_date')->pluck('total','activity_date');
        $series=[];for($i=$range-1;$i>=0;$i--) {$date=$today->subDays($i)->toDateString();$series[$date]=(int)($daily[$date] ?? 0);}
        $table=match($tab){'streaks'=>'venue_reward_progress','testimonials'=>'venue_reward_testimonials','ledger'=>'venue_reward_grants',default=>null};
        $rows=null;
        if ($table) {
            $query=DB::table($table)->join('venues','venues.id','=',$table.'.venue_id')
                ->select($table.'.*','venues.name as venue_name','venues.slug as venue_slug');
            if ($search!=='') $query->where(fn($q)=>$q->where('venues.name','like','%'.$search.'%')->orWhere('venues.slug','like','%'.$search.'%'));
            if ($tab==='streaks') $query->orderByDesc('current_streak')->orderByDesc('last_active_date')->orderBy('venue_id');
            elseif ($tab==='testimonials') $query->orderByRaw("CASE WHEN status='pending' THEN 0 ELSE 1 END")->orderByDesc($table.'.updated_at');
            else $query->orderByDesc('earned_at')->orderByDesc($table.'.id');
            $rows=$query->paginate(25)->withQueryString();
        }
        return response()->view('platform.rewards-admin',compact('tab','range','search','stats','series','rows'))->header('Cache-Control','private, no-store');
    }

    public function review(Request $request,int $record)
    {
        $data=$request->validate(['decision'=>'required|in:approve,reject','note'=>'required|string|max:1000','verified'=>'required_if:decision,approve|accepted']);
        PlatformRewards::review($record,$request->user(),$data['decision']==='approve',$data['note']);
        return back()->with('status','Testimonial review recorded.');
    }

    public function revoke(Request $request,int $grant)
    {
        $data=$request->validate(['reason'=>'required|string|max:500','confirmed'=>'accepted']);
        PlatformRewards::revoke($grant,$request->user(),$data['reason']);
        return back()->with('status','Unused reward credit revoked.');
    }
}
