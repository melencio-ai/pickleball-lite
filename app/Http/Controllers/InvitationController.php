<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venue;
use App\Services\VenueContext;
use App\Services\LegalPolicies;
use Illuminate\Support\Facades\DB;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class InvitationController extends Controller
{
    public function show(Request $request,string $token)
    {
        $invite = VenueInvitations::find($token);
        $record = Venue::findOrFail($invite->venue_id);
        $existing = User::whereRaw('LOWER(email) = ?',[$invite->email])->first();
        if ($existing && !$request->user()) {
            $request->session()->put('url.intended',route('app.invite.show',['token'=>$token]));
            return redirect()->route('app.login')->with('status','Sign in with the invited email to accept your venue access.');
        }
        if ($request->user()) abort_unless($existing && $existing->id === $request->user()->id,403);

        $legal = LegalPolicies::context(subscription:$invite->role === 'owner');
        return response()->view('platform.invite',compact('invite','record','token','existing','legal'))
            ->withHeaders(['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow']);
    }

    public function accept(Request $request,string $token)
    {
        $data = $request->validate(['name'=>'nullable|string|max:100','password'=>'nullable|string|min:12|max:72|confirmed']);
        $user = DB::transaction(function () use ($request,$token,$data) {
            $invite = VenueInvitations::find($token);
            $legal = LegalPolicies::context(subscription:$invite->role === 'owner',lock:true);
            LegalPolicies::validate($request->only('legal_fingerprint','legal_accepted','subscription_accepted'),$legal);
            $user = VenueInvitations::accept($token,$request->user(),$data);
            LegalPolicies::record($legal,'user',$user->id,$invite->venue_id,$user->id);
            return $user;
        },3);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('app.venues')->with('status','Venue access accepted.');
    }

    public function issueStaff(Request $request)
    {
        $data = $request->validate(['email'=>'required|email|max:255','role'=>['required',Rule::in(['staff','manager'])]]);
        $url = VenueInvitations::issue(VenueContext::venue(),$data['email'],$data['role'],$request->user());

        return back()->with('status','Invitation created. Share it privately with the invited person.')->with('invite_url',$url);
    }
}
