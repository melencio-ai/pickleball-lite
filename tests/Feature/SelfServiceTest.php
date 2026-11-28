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
        $this->get($this->appUrl('/register'))->assertOk()->assertSee('30 days free')->assertSee('₱10,000/year')->assertDontSee('extensions')->assertDontSee('₱5,000');
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
            $this->travelTo(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)->subDays(7)->subSecond());
            $this->get($this->workspace($venue,'/subscription'))->assertOk()->assertDontSee('Extend free trial')->assertDontSee('extensions remaining');
            $this->post($this->workspace($venue,'/subscription/extend'),['extension_count'=>$i])->assertSessionHasErrors('trial');
            $this->travelTo(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)->subDays(7));
            $this->get($this->workspace($venue,'/subscription'))->assertOk()->assertSee('Extend free trial');
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


    public function test_yearly_offer_appears_at_day_fourteen_is_delivered_once_and_requires_verified_payment(): void
    {
        $this->travelTo(now()->setTime(10,0));
        [$owner,$venue]=$this->signup();
        $this->verify($owner,$venue);
        $created=\Carbon\CarbonImmutable::parse($owner->created_at);
        $subscriptionUrl=$this->workspace($venue,'/subscription');
        $claimUrl=$subscriptionUrl.'/upgrade';
        $this->travelTo($created->addDays(14)->subSecond());
        $this->get($subscriptionUrl)->assertOk()->assertSee('₱10,000')->assertDontSee('₱5,000')->assertDontSee('Claim offer');
        $this->post($claimUrl,['plan'=>'yearly_promo'])->assertSessionHasErrors('plan');
        \App\Services\SubscriptionPlans::sweep();
        $this->assertDatabaseMissing('venue_notifications',['category'=>'promotion']);
        $this->travelTo($created->addDays(14));
        $this->get($subscriptionUrl)->assertOk()->assertSee('₱5,000')->assertSee('Claim offer');
        $this->get($this->workspace($venue,'/onboarding'))->assertOk()->assertSee('data-subscription-promo',false);
        $this->patch($this->appUrl('/notifications/preferences'),['enabled'=>['account']])->assertRedirect();
        \App\Services\SubscriptionPlans::sweep();
        $this->assertDatabaseMissing('venue_notifications',['category'=>'promotion']);
        $this->assertNull(TrialAccess::subscription($venue)->promo_email_attempted_at);
        $this->patch($this->appUrl('/notifications/preferences'),['enabled'=>['account','promotion']])->assertRedirect();
        Mail::shouldReceive('raw')->once()->withArgs(fn($body,$callback)=>str_contains($body,'PHP 5,000') && str_contains($body,'/subscription'))->andReturnNull();
        \App\Services\SubscriptionPlans::sweep();
        \App\Services\SubscriptionPlans::sweep();
        $this->assertSame(1,DB::table('venue_notifications')->where('category','promotion')->count());
        $this->assertNotNull(TrialAccess::subscription($venue)->promo_email_sent_at);
        $this->post($claimUrl,['plan'=>'yearly_promo'])->assertRedirect();
        $this->post($claimUrl,['plan'=>'yearly_promo'])->assertRedirect();
        $sub=TrialAccess::subscription($venue);
        $this->assertSame('yearly_promo',$sub->requested_plan);
        $this->assertNull($sub->promo_redeemed_at);
        $this->assertSame('trial',$sub->status);
        $this->assertDatabaseCount('subscription_payments',0);
        $admin=User::where('email','self-service-admin@example.test')->firstOrFail();
        LegalPolicies::record(LegalPolicies::context(),'user',$admin->id,$admin->venue_id,$admin->id);
        $this->actingAs($admin);
        $paymentUrl=$this->appUrl('/admin/venues/'.$venue->id.'/billing/payments');
        $payment=['kind'=>'yearly_promo','amount'=>'5000.00','reference'=>'PROMO-001','received_on'=>now()->toDateString()];
        $this->post($paymentUrl,array_replace($payment,['amount'=>'4999.00']))->assertSessionHasErrors('amount');
        $this->post($paymentUrl,$payment)->assertRedirect();
        $paid=TrialAccess::subscription($venue);
        $this->assertSame('active',$paid->status);
        $this->assertSame('yearly_promo',$paid->billing_plan);
        $this->assertSame(\Carbon\CarbonImmutable::parse($sub->trial_ends_at)->setTimezone($venue->timezone)->addYearNoOverflow()->toDateString(),$paid->renewal_date);
        $this->assertNotNull($paid->promo_redeemed_at);
        $this->assertDatabaseHas('subscription_payments',['kind'=>'yearly_promo','amount'=>500000,'reference'=>'PROMO-001']);
        $this->post($paymentUrl,array_replace($payment,['reference'=>'PROMO-002']))->assertSessionHasErrors('kind');
        $this->actingAs($owner)->get($subscriptionUrl)->assertOk()->assertDontSee('Claim offer');
        $this->post($claimUrl,['plan'=>'yearly_promo'])->assertSessionHasErrors('plan');
        $this->actingAs($admin)->post($paymentUrl,array_replace($payment,['kind'=>'yearly','amount'=>'10000.00','reference'=>'YEAR-001']))->assertRedirect();
        $renewed=TrialAccess::subscription($venue);
        $this->assertSame('yearly',$renewed->billing_plan);
        $this->assertSame(\Carbon\CarbonImmutable::parse($paid->renewal_date)->addYearNoOverflow()->toDateString(),$renewed->renewal_date);
        $this->travelTo(\Carbon\CarbonImmutable::parse($renewed->renewal_date,$venue->timezone));
        $this->assertFalse(TrialAccess::allowed($venue));
    }

    public function test_messenger_variants_preserve_recipient_and_other_contact_links(): void
    {
        $variants=\App\Services\MessengerLinks::variants('https://www.facebook.com/messages/t/pickleliteapp');
        $this->assertSame('https://m.me/pickleliteapp',$variants['mobile']);
        $this->assertSame('https://www.facebook.com/messages/t/pickleliteapp',$variants['desktop']);
        $this->assertSame('https://www.facebook.com/messages/t/12345',\App\Services\MessengerLinks::variants('https://m.me/12345')['desktop']);
        $this->assertSame('https://example.com/contact',\App\Services\MessengerLinks::variants('https://example.com/contact')['mobile']);
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
        $this->get($this->appUrl('/notifications/drawer'))->assertOk()->assertHeader('X-Pickle-Notification-Drawer','1')
            ->assertDontSee('New court booking')->assertDontSee('class="site-header"',false);
        $this->postJson($this->appUrl('/notifications/'.$n->id.'/read'))->assertNotFound();
        $this->actingAs($owner)->getJson($this->appUrl('/notifications/count'))->assertJson(['unread'=>1]);
        $this->get($this->appUrl('/notifications'))->assertOk()->assertSee('New court booking');
        $this->get($this->appUrl('/notifications/drawer'))->assertOk()->assertHeader('X-Pickle-Notification-Drawer','1')
            ->assertSee('data-drawer-unread="1"',false)->assertSee('New court booking');
        $this->postJson($this->appUrl('/notifications/'.$n->id.'/read'))->assertOk()->assertJson(['unread'=>0,'message'=>'Notification marked as read.']);
        VenueNotifications::emit($venue,'booking','new-2','Another court booking','Court 2');
        $this->postJson($this->appUrl('/notifications/read-all'))->assertOk()->assertJson(['unread'=>0,'message'=>'All notifications marked as read.']);
        $this->post($this->appUrl('/notifications/'.$n->id.'/read'))->assertRedirect();
        $this->getJson($this->appUrl('/notifications/count'))->assertJson(['unread'=>0]);
        $owner->venues()->detach($venue->id);
        $this->get($this->appUrl('/notifications'))->assertOk()->assertDontSee('New court booking');
        $this->get($this->appUrl('/notifications/drawer'))->assertOk()->assertDontSee('New court booking')->assertDontSee('Another court booking');
    }
}
