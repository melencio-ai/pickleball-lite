<?php

namespace App\Http\Controllers;

use App\Services\PlatformEmail;
use App\Services\VenueInvitations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmailAdminController extends Controller
{
    public function index()
    {
        $settings = PlatformEmail::settings();
        $logs = DB::table('platform_email_logs')->orderByDesc('id')->paginate(25);
        $today = now('Asia/Manila')->startOfDay()->setTimezone(config('app.timezone'));
        $counts = DB::table('platform_email_logs')->where('created_at','>=',$today)
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total','status');
        // Never pass ciphertext or decrypted credentials into the template.
        $state = [
            'configured'=>(bool)$settings, 'enabled'=>PlatformEmail::active($settings),
            'tested_at'=>$settings?->verified_at,
            'registration'=>(bool)$settings?->registration_enabled,
            'recovery'=>(bool)$settings?->recovery_enabled,
            'environment_fallback'=>!$settings && PlatformEmail::ready(),
        ];
        return response()->view('platform.email',compact('state','logs','counts'))
            ->header('Cache-Control','private, no-store');
    }

    public function save(Request $request)
    {
        $data = $request->validate(['password'=>'required|string|max:64']);
        $password = preg_replace('/\s+/', '', $data['password']);
        if (!preg_match('/\A[a-zA-Z0-9]{16}\z/D',$password)) {
            throw ValidationException::withMessages(['password'=>'Enter a 16-character Google app password. Spaces are ignored.']);
        }
        DB::transaction(function () use ($request,$password) {
            $record = DB::table('platform_email_settings')->where('id',1)->lockForUpdate()->first();
            DB::table('platform_email_settings')->updateOrInsert(['id'=>1],[
                'password_ciphertext'=>Crypt::encryptString($password),
                'enabled'=>false,'registration_enabled'=>false,'recovery_enabled'=>false,
                'revision'=>($record?->revision ?? 0)+1,'verified_at'=>null,
                'created_at'=>$record?->created_at ?? now(),'updated_at'=>now(),
            ]);
            VenueInvitations::audit($request->user(),null,'email.credentials_saved');
        },3);
        return back()->with('status','SMTP credentials saved. Send a test email before enabling the service.');
    }

    public function test(Request $request)
    {
        $settings = PlatformEmail::settings();
        if (!$settings) throw ValidationException::withMessages(['email'=>'Save the Gmail app password first.']);
        $sent = PlatformEmail::send('test',$request->user()->email,'PickleLite App SMTP test',
            "Your PickleLite App email service submitted this test message.\n\nConfirm that you received it before enabling the service.",$settings);
        if (!$sent) throw ValidationException::withMessages(['email'=>'SMTP submission failed. Check the app password, Google account and Railway SMTP access.']);
        $updated = DB::table('platform_email_settings')->where('id',1)->where('revision',$settings->revision)
            ->update(['verified_at'=>now(),'updated_at'=>now()]);
        if (!$updated) throw ValidationException::withMessages(['email'=>'Credentials changed during the test. Test the current settings again.']);
        VenueInvitations::audit($request->user(),null,'email.test_submitted');
        return back()->with('status','SMTP accepted the test message. Check your inbox and spam folder before enabling.');
    }

    public function activation(Request $request)
    {
        $data = $request->validate(['enabled'=>'required|boolean','registration'=>'sometimes|boolean','recovery'=>'sometimes|boolean',
            'received'=>'sometimes|accepted']);
        DB::transaction(function () use ($request,$data) {
            $settings = DB::table('platform_email_settings')->where('id',1)->lockForUpdate()->first();
            if (!$settings) throw ValidationException::withMessages(['email'=>'Save the Gmail app password first.']);
            $enabled = $request->boolean('enabled');
            if ($enabled && (!$settings->verified_at || !$request->boolean('received'))) {
                throw ValidationException::withMessages(['email'=>'Send a successful test and confirm receipt before enabling SMTP.']);
            }
            DB::table('platform_email_settings')->where('id',1)->update([
                'enabled'=>$enabled,'registration_enabled'=>$enabled && $request->boolean('registration'),
                'recovery_enabled'=>$enabled && $request->boolean('recovery'),'updated_at'=>now(),
            ]);
            VenueInvitations::audit($request->user(),null,'email.activation_changed',[
                'enabled'=>$enabled,'registration'=>$enabled && $request->boolean('registration'),
                'recovery'=>$enabled && $request->boolean('recovery'),
            ]);
        },3);
        return back()->with('status','Email service settings updated.');
    }
}
