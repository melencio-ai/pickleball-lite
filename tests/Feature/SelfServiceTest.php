<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Venue;
use App\Services\LegalPolicies;
use App\Services\TrialAccess;
use App\Services\VenueNotifications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SelfServiceTest extends TestCase
{
    use RefreshDatabase;

    private function appUrl(string $path=''): string { return 'http://'.config('pickle.app_host').$path; }
    private function workspace(Venue $venue,string $path=''): string { return $this->appUrl('/venues/'.$venue->slug.$path); }
    private function publicUrl(Venue $venue,string $path=''): string { return 'http://'.config('pickle.book_host').'/'.$venue->slug.$path; }

    private function publishTestPolicies(): void
    {
        $this->seed();
        $venue=Venue::where('legacy_slot',1)->firstOrFail();
        $admin=$venue->users()->create(['name'=>'Legal Admin','email'=>'self-service-admin@example.test','password'=>'test-password-123','role'=>'owner']);
        $admin->forceFill(['platform_admin'=>true])->save();
        foreach(DB::table('legal_documents')->get() as $doc) {
            $id=DB::table('legal_document_versions')->insertGetId(['document_slug'=>$doc->slug,'version'=>1,'body'=>'Test policy',
                'operator_details'=>'{}','published_by'=>$admin->id,'published_at'=>now()]);
            DB::table('legal_documents')->where('slug',$doc->slug)->update(['current_version_id'=>$id]);
        }
    }

    private function ready(): void
    {
        config(['selfservice.enabled'=>true,'mail.default'=>'smtp','mail.mailers.smtp.host'=>'smtp.example.test','mail.from.address'=>'support@example.test']);
        $this->publishTestPolicies();
        Mail::shouldReceive('raw')->once()->withArgs(fn($body,$callback)=>str_contains($body,'Verify your email'))->andReturnNull();
    }

    private function signup(): array
    {
        $this->ready();
        $ctx=LegalPolicies::context(subscription:true);
        $this->post($this->appUrl('/register'),['name'=>'Self Service Owner','email'=>'self-owner@example.test','password'=>'sample-password-123',
            'password_confirmation'=>'sample-password-123','legal_fingerprint'=>$ctx['fingerprint'],'legal_accepted'=>'1','subscription_accepted'=>'1'])
            ->assertRedirect($this->appUrl('/verify-email'));
        $owner=User::where('email','self-owner@example.test')->firstOrFail();
        return [$owner,$owner->venue()->firstOrFail()];
    }

    private function verify(User $owner,Venue $venue): void
    {
        $url=URL::temporarySignedRoute('app.verify.complete',now()->addHour(),['id'=>$owner->id,'hash'=>sha1($owner->email)]);
        $this->get($url)->assertRedirect($this->workspace($venue,'/onboarding'));
    }

    public function test_public_registration_requires_feature_flag_smtp_and_published_policies(): void
    {
        $this->get($this->appUrl('/register'))->assertNotFound();
        config(['selfservice.enabled'=>true]);
        $this->get($this->appUrl('/register'))->assertNotFound();
        config(['mail.default'=>'smtp','mail.mailers.smtp.host'=>'smtp.example.test','mail.from.address'=>'support@example.test']);
        $this->get($this->appUrl('/register'))->assertNotFound();
        $this->publishTestPolicies();
        $this->get($this->appUrl('/register'))->assertOk()->assertSee('30 days free');
        $this->get('http://'.config('pickle.product_host'))->assertOk()->assertSee('Watch a 5-minute full walkthrough')
            ->assertSee('Create a free account')->assertSee('Walkthrough video coming soon.')
            ->assertDontSee("Let's get your venue online")->assertDontSee('Send enquiry');
        $this->post($this->appUrl('/register'),['name'=>'Unaccepted','email'=>'reject@example.test','password'=>'test-password-123','password_confirmation'=>'test-password-123'])
            ->assertSessionHasErrors('legal_accepted');
        $this->assertDatabaseMissing('users',['email'=>'reject@example.test']);
    }

    public function test_signup_verification_and_seven_step_launch_are_resumable(): void
    {
        config(['pickle.walkthrough_video_url'=>'https://youtu.be/AbCdEf12345']);
        [$owner,$venue]=$this->signup();
        $this->assertFalse($venue->published);
        $this->assertNull(TrialAccess::subscription($venue)->trial_started_at);
        $this->assertDatabaseHas('venue_memberships',['venue_id'=>$venue->id,'user_id'=>$owner->id,'role'=>'owner']);
        $this->assertDatabaseHas('legal_acceptances',['subject_type'=>'venue_subscription','subject_id'=>$venue->id,'accepted_by'=>$owner->id]);
        $this->get($this->workspace($venue,'/calendar'))->assertRedirect($this->appUrl('/verify-email'));
        $this->get($this->appUrl('/verify-email/'.$owner->id.'/'.sha1($owner->email)))->assertForbidden();
        $this->verify($owner,$venue);
        $sub=TrialAccess::subscription($venue);
        $this->assertSame('trial',$sub->status);
        $this->assertEquals(30,\Carbon\CarbonImmutable::parse($sub->trial_started_at)->diffInDays(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)));
        $this->get($this->workspace($venue,'/onboarding'))->assertOk()->assertSee('Tell us about your venue');
        $this->post($this->workspace($venue,'/onboarding/7'),['launch_confirmed'=>'1'])->assertStatus(409);
        $this->post($this->workspace($venue,'/onboarding/1'),['name'=>'Self Test Courts','slug'=>'self-test-courts','address'=>'Test address','phone'=>'09171234567','timezone'=>'Asia/Manila'])->assertRedirect();
        $venue->refresh();
        $this->assertSame('self-test-courts',$venue->slug);
        $this->post($this->workspace($venue,'/onboarding/2'),['court_count'=>2])->assertRedirect();
        $this->get($this->workspace($venue,'/onboarding'))->assertOk()->assertSee('When are you open?');
        Auth::logout();
        $this->post($this->appUrl('/login'),['email'=>$owner->email,'password'=>'sample-password-123'])->assertRedirect($this->workspace($venue,'/onboarding'));
        $this->post($this->workspace($venue,'/onboarding/3'),['days'=>[0,1,2,3,4,5,6],'opens_at'=>'07:00','closes_at'=>'22:00'])->assertRedirect();
        $this->post($this->workspace($venue,'/onboarding/4'),['rate'=>'450','durations'=>[60,120]])->assertRedirect();
        $this->post($this->workspace($venue,'/onboarding/5'),['payment_method'=>'venue'])->assertRedirect();
        $this->post($this->workspace($venue,'/onboarding/6'),['brand_color'=>'#155542'])->assertRedirect();
        $this->get($this->workspace($venue,'/onboarding'))->assertOk()->assertSee('Review and launch');
        $this->post($this->workspace($venue,'/onboarding/7'),['cancellation_policy'=>'Contact the venue for changes.','launch_confirmed'=>'1'])
            ->assertRedirect($this->workspace($venue,'/calendar'))->assertSessionHas('walkthrough_venue_id',$venue->id);
        $this->get($this->workspace($venue,'/calendar'))->assertOk()->assertSee('data-walkthrough-auto',false)
            ->assertSee('https://www.youtube-nocookie.com/embed/AbCdEf12345')->assertSee('Play walkthrough');
        $this->get($this->workspace($venue,'/calendar'))->assertOk()->assertDontSee('data-walkthrough-auto',false)
            ->assertSee('Watch walkthrough');
        config(['pickle.walkthrough_video_url'=>null]);
        $this->withSession(['walkthrough_venue_id'=>$venue->id])->get($this->workspace($venue,'/calendar'))
            ->assertOk()->assertSee('Walkthrough video coming soon')->assertDontSee('data-video-src',false);
        $venue->refresh();
        $this->assertTrue($venue->published);
        $this->assertTrue($venue->acceptsPublicBookings());
        $this->assertSame(2,$venue->courts()->count());
        $this->get($this->publicUrl($venue))->assertOk()->assertSee('Self Test Courts');
    }

    public function test_trial_extensions_are_capped_and_expiry_preserves_prior_booking_links(): void
    {
        $this->travelTo(now()->setDate(2030,1,1)->setTime(0,0));
        $this->seed();
        $venue=Venue::where('legacy_slot',1)->firstOrFail();
        $venue->update(['self_service'=>true,'status'=>'active','published'=>true]);
        $owner=$venue->users()->create(['name'=>'Trial owner','email'=>'trial@example.test','password'=>'test-password-123','role'=>'owner']);
        DB::transaction(fn()=>TrialAccess::start($venue));
        $sub=TrialAccess::subscription($venue);
        $ctx=LegalPolicies::context($venue);
        $date=now($venue->timezone)->addDay()->toDateString();
        $data=['court_id'=>$venue->courts()->firstOrFail()->id,'date'=>$date,'start'=>'19:00','duration'=>60,'quoted_total'=>40000,'customer_name'=>'Player','customer_phone'=>'09171234567'];
        $this->post($this->publicUrl($venue,'/bookings'),$data)->assertRedirect();
        $record=Booking::firstOrFail();
        $this->actingAs($owner)->post($this->workspace($venue,'/subscription/extend'),['extension_count'=>0])->assertSessionHasErrors('trial');
        for($i=0;$i<3;$i++) {
            $sub=TrialAccess::subscription($venue);
            $this->travelTo(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)->subDays(3));
            $this->post($this->workspace($venue,'/subscription/extend'),['extension_count'=>$i])->assertRedirect();
            $this->post($this->workspace($venue,'/subscription/extend'),['extension_count'=>$i])->assertSessionHasErrors('trial');
        }
        $sub=TrialAccess::subscription($venue);
        $this->assertSame(3,(int)$sub->trial_extensions_count);
        $this->assertEquals(75,\Carbon\CarbonImmutable::parse($sub->trial_started_at)->diffInDays(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)));
        $this->assertDatabaseCount('trial_extensions',3);
        $this->travelTo(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)->addSecond());
        $this->assertFalse($venue->fresh()->acceptsPublicBookings());
        $this->get($this->publicUrl($venue))->assertNotFound();
        $this->get($this->publicUrl($venue,'/bookings/confirmed'))->assertOk()->assertSee(strtoupper($record->reference));
        $this->post($this->workspace($venue,'/bookings'),array_replace($data,['date'=>now($venue->timezone)->addDay()->toDateString()]))->assertSessionHasErrors('subscription');
        $this->get($this->workspace($venue,'/subscription'))->assertOk()->assertSee('Your free trial has ended');
        VenueNotifications::sweep();VenueNotifications::sweep();
        $this->assertSame('trial_expired',TrialAccess::subscription($venue)->status);
        $this->assertSame(1,DB::table('venue_notifications')->where('event_key',$venue->id.':trial:expired-3')->count());
    }

    public function test_notification_preferences_and_access_are_user_scoped(): void
    {
        $this->seed();
        $venue=Venue::where('legacy_slot',1)->firstOrFail();
        $owner=$venue->users()->create(['name'=>'Owner','email'=>'notify-owner@example.test','password'=>'test-password-123','role'=>'owner']);
        $staff=$venue->users()->create(['name'=>'Staff','email'=>'notify-staff@example.test','password'=>'test-password-123','role'=>'staff']);
        $this->actingAs($staff)->patch($this->appUrl('/notifications/preferences'),['enabled'=>['account']])->assertRedirect();
        VenueNotifications::emit($venue,'booking','new-1','New court booking','Court 1');
        VenueNotifications::emit($venue,'booking','new-1','New court booking','Court 1');
        $this->assertDatabaseCount('venue_notifications',1);
        $n=DB::table('venue_notifications')->first();
        $this->getJson($this->appUrl('/notifications/count'))->assertJson(['unread'=>0]);
        $this->post($this->appUrl('/notifications/'.$n->id.'/read'))->assertNotFound();
        $this->actingAs($owner)->getJson($this->appUrl('/notifications/count'))->assertJson(['unread'=>1]);
        $this->get($this->appUrl('/notifications'))->assertOk()->assertSee('New court booking');
        $this->post($this->appUrl('/notifications/'.$n->id.'/read'))->assertRedirect();
        $this->getJson($this->appUrl('/notifications/count'))->assertJson(['unread'=>0]);
        $owner->venues()->detach($venue->id);
        $this->get($this->appUrl('/notifications'))->assertOk()->assertDontSee('New court booking');
    }
}
