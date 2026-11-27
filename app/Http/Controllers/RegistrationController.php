<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Venue;
use App\Services\AuthDestination;
use App\Services\LegalPolicies;
use App\Services\SelfService;
use App\Services\TrialAccess;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class RegistrationController extends Controller
{
    public function create()
    {
        abort_unless(SelfService::ready(),404);
        return response()->view('auth.register',['legal'=>LegalPolicies::context(subscription:true)])
            ->header('Cache-Control','private, no-store');
    }

    public function store(Request $request)
    {
        abort_unless(SelfService::ready(),404);
        $email=$request->input('email');
        if(is_string($email)) $request->merge(['email'=>strtolower(trim($email))]);
        $data=$request->validate([
            'name'=>'required|string|max:100','email'=>['required','email','max:255',Rule::unique('users','email')],
            'password'=>['required','string','min:12','max:72','confirmed',function($attribute,$value,$fail) {
                if(strlen($value)>72) $fail('Use a password of at most 72 bytes.');
            }],
            'website'=>'nullable|string|max:0',
        ]);
        $user=DB::transaction(function() use($request,$data) {
            $legal=LegalPolicies::context(subscription:true,lock:true);
            abort_unless($legal['terms'] && $legal['privacy'] && $legal['subscriptionVersion'],409);
            LegalPolicies::validate($request->only('legal_fingerprint','legal_accepted','subscription_accepted'),$legal);
            $venue=Venue::create(['name'=>'Your venue','address'=>'','phone'=>'','timezone'=>'Asia/Manila',
                'status'=>'onboarding','published'=>false,'self_service'=>true]);
            $user=$venue->users()->create(['name'=>$data['name'],'email'=>$data['email'],'password'=>$data['password'],'role'=>'owner']);
            $user->forceFill(['self_registered'=>true])->save();
            DB::table('venue_subscriptions')->insert(['venue_id'=>$venue->id,'status'=>'onboarding','created_at'=>now(),'updated_at'=>now()]);
            DB::table('venue_onboarding')->insert(['venue_id'=>$venue->id,'step'=>1,'created_at'=>now(),'updated_at'=>now()]);
            foreach(range(0,6) as $day) $venue->hours()->create(['weekday'=>$day,'opens_at'=>'07:00','closes_at'=>'22:00','closed'=>true]);
            LegalPolicies::record($legal,'user',$user->id,$venue->id,$user->id);
            VenueInvitations::audit($user,$venue,'self_service.registered');
            return $user;
        },3);
        Auth::login($user);
        $request->session()->regenerate();
        $sent=RateLimiter::attempt('verify-mail:'.$user->id,3,fn()=>SelfService::sendVerification($user),600);
        return redirect()->route('app.verify.notice')->with('status',$sent?'Verification email sent.':'We could not send the email. Try resending in a few minutes.');
    }

    public function notice(Request $request)
    {
        if(!$request->user()->self_registered || $request->user()->email_verified_at) return redirect()->to(AuthDestination::home($request->user()));
        return response()->view('auth.verify')->header('Cache-Control','private, no-store');
    }

    public function resend(Request $request)
    {
        $user=$request->user();
        abort_unless($user->self_registered && !$user->email_verified_at,403);
        $sent=RateLimiter::attempt('verify-mail:'.$user->id,3,fn()=>SelfService::sendVerification($user),600);
        return back()->with('status',$sent?'Verification email sent.':'Email could not be sent. Try again in a few minutes.');
    }

    public function verify(Request $request,int $id,string $hash)
    {
        $user=$request->user();
        abort_unless($user->id===$id && $user->self_registered && hash_equals(sha1($user->email),$hash),403);
        DB::transaction(function() use($user) {
            $record=User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            if($record->email_verified_at) return;
            $venue=Venue::whereKey($record->venue_id)->lockForUpdate()->firstOrFail();
            abort_unless($venue->self_service && $venue->members()->where('users.id',$record->id)->wherePivot('role','owner')->exists(),403);
            $record->forceFill(['email_verified_at'=>now()])->save();
            TrialAccess::start($venue);
            VenueInvitations::audit($record,$venue,'self_service.email_verified');
        },3);
        $request->session()->regenerate();
        return redirect()->to(AuthDestination::home($user->fresh()))->with('status','Email verified. Your 30-day free trial has started.');
    }
}
