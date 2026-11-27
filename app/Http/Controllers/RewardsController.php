<?php

namespace App\Http\Controllers;

use App\Services\PlatformRewards;
use App\Services\TrialAccess;
use App\Services\VenueContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RewardsController extends Controller
{
    public function index()
    {
        $venue=VenueContext::venue();
        abort_unless(PlatformRewards::visible($venue,TrialAccess::subscription($venue)),404);
        $state=PlatformRewards::summary($venue);
        $testimonial=DB::table('venue_reward_testimonials')->where('venue_id',$venue->id)->first();
        return response()->view('platform.rewards',compact('venue','state','testimonial'))->header('Cache-Control','private, no-store');
    }

    public function submit(Request $request)
    {
        $data=$request->validate(['submission_reference'=>'required|string|max:500','submitted'=>'accepted','marketing_consent'=>'sometimes|accepted']);
        PlatformRewards::submit(VenueContext::venue(),$request->user(),trim($data['submission_reference']),$request->boolean('marketing_consent'));
        return VenueContext::redirect('settings.rewards')->with('status','Video testimonial submitted for review.');
    }

    public function withdraw(Request $request)
    {
        PlatformRewards::withdrawMarketing(VenueContext::venue(),$request->user());
        return VenueContext::redirect('settings.rewards')->with('status','Marketing permission withdrawn. Your earned reward is unchanged.');
    }

    public function apply(Request $request,int $grant)
    {
        $request->validate(['confirmed'=>'accepted']);
        PlatformRewards::apply(VenueContext::venue(),$request->user(),$grant);
        return VenueContext::redirect('settings.rewards')->with('status','Free subscription months applied.');
    }
}
