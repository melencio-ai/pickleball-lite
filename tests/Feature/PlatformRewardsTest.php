<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Services\PlatformRewards;
use App\Services\TrialAccess;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformRewardsTest extends TestCase
{
    use RefreshDatabase;

    private function app(string $path=''): string {return 'http://'.config('pickle.app_host').$path;}
    private function url(Venue $v,string $path=''): string {return $this->app('/venues/'.$v->slug.$path);}

    private function venue(string $slug='reward-courts',string $status='active'): array
    {
        $v=Venue::create(['name'=>$slug,'slug'=>$slug,'address'=>'Test address','phone'=>'','timezone'=>'Asia/Manila','status'=>'active','published'=>true,'self_service'=>true]);
        $owner=$v->users()->create(['name'=>'Owner','email'=>$slug.'-owner@example.test','password'=>'test-password-123','role'=>'owner']);
        $owner->forceFill(['email_verified_at'=>now()])->save();
        $staff=$v->users()->create(['name'=>'Staff','email'=>$slug.'-staff@example.test','password'=>'test-password-123','role'=>'staff']);
        foreach(range(0,6) as $day) $v->hours()->create(['weekday'=>$day,'opens_at'=>'07:00','closes_at'=>'22:00']);
        $v->courts()->create(['name'=>'Court 1','hourly_rate'=>40000,'active'=>true]);
        DB::table('venue_subscriptions')->insert([
            'venue_id'=>$v->id,'status'=>$status,'renewal_date'=>$status==='active'?now('Asia/Manila')->addYear()->toDateString():null,
            'trial_started_at'=>$status==='trial'?now():null,'trial_ends_at'=>$status==='trial'?now()->addDays(30):null,
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        return [$v,$owner,$staff];
    }

    private function day(int $n): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-27 09:00:00','Asia/Manila')->addDays($n));
    }

    public function test_only_authorized_operational_activity_counts_once_per_venue_local_day(): void
    {
        $this->day(0);
        [$venue,$owner,$staff]=$this->venue();
        [$other,$otherOwner]=$this->venue('other-rewards');
        $this->actingAs($owner)->get($this->url($venue,'/calendar?fragment=grid'))->assertOk();
        $this->getJson($this->app('/notifications/count'))->assertOk();
        $this->get($this->url($venue,'/rewards'))->assertOk();
        $this->get($this->url($venue,'/calendar'),['Sec-Fetch-Dest'=>'empty'])->assertOk();
        $this->get($this->url($venue,'/calendar'),['Sec-Purpose'=>'prefetch'])->assertOk();
        $this->assertDatabaseCount('venue_reward_days',0);
        $this->get($this->url($other,'/calendar'))->assertForbidden();
        $this->get($this->url($venue,'/calendar'),['Sec-Fetch-Dest'=>'document'])->assertOk()->assertSee('1-day streak');
        $this->get($this->url($venue,'/open-play'))->assertOk();
        $this->actingAs($staff)->get($this->url($venue,'/calendar'))->assertOk()->assertDontSee('class="rewards-widget"',false);
        $this->get($this->url($venue,'/rewards'))->assertForbidden();
        $this->assertDatabaseCount('venue_reward_days',1);
        $this->day(1);
        $this->actingAs($staff)->get($this->url($venue,'/calendar'))->assertOk();
        $this->assertSame(2,PlatformRewards::summary($venue)['streak']);
        $this->day(3);
        $this->assertSame(0,PlatformRewards::summary($venue)['streak']);
        $this->actingAs($owner)->get($this->url($venue,'/calendar'))->assertOk()->assertSee('1-day streak');
        $this->assertSame(2,PlatformRewards::summary($venue)['longest']);
        $this->assertDatabaseCount('venue_reward_days',3);
        config(['pickle.demo_slug'=>$other->slug]);
        $this->actingAs($otherOwner)->get($this->url($other,'/calendar'))->assertOk()->assertDontSee('class="rewards-widget"',false);
        $this->assertDatabaseCount('venue_reward_days',3);
        $this->travelBack();
    }

    public function test_hundred_and_three_hundred_consecutive_days_are_one_time_cumulative_awards(): void
    {
        $this->day(0);
        [$venue,$owner,$staff]=$this->venue();
        for($i=0;$i<300;$i++) {
            $this->day($i);
            PlatformRewards::record($venue,$i%2?$staff:$owner);
            if ($i===98) $this->assertDatabaseCount('venue_reward_grants',0);
            if ($i===99) $this->assertDatabaseHas('venue_reward_grants',['venue_id'=>$venue->id,'milestone'=>'streak_100','months'=>1]);
            if ($i===298) $this->assertDatabaseCount('venue_reward_grants',1);
        }
        PlatformRewards::record($venue,$owner);
        $this->assertDatabaseCount('venue_reward_days',300);
        $this->assertDatabaseCount('venue_reward_grants',2);
        $this->assertDatabaseHas('venue_reward_grants',['venue_id'=>$venue->id,'milestone'=>'streak_300','months'=>2]);
        $this->assertSame(300,PlatformRewards::summary($venue)['streak']);
        $this->assertEquals(3,PlatformRewards::summary($venue)['available']);
        $this->day(301);
        $this->assertSame(0,PlatformRewards::summary($venue)['streak']);
        DB::table('venue_subscriptions')->where('venue_id',$venue->id)->update(['renewal_date'=>now('Asia/Manila')->addYear()->toDateString()]);
        for($i=301;$i<601;$i++) {$this->day($i);PlatformRewards::record($venue,$owner);}
        $this->assertDatabaseCount('venue_reward_grants',2);
        $this->assertSame(300,PlatformRewards::summary($venue)['streak']);
        $this->assertEquals(3,PlatformRewards::summary($venue)['available']);
        $this->travelBack();
    }

    public function test_video_submission_approval_and_reward_application_are_scoped_and_audited(): void
    {
        $this->day(0);
        [$venue,$owner,$staff]=$this->venue('trial-rewards','trial');
        [$other,$admin]=$this->venue('admin-rewards');
        $admin->forceFill(['platform_admin'=>true])->save();
        $this->actingAs($owner)->post($this->url($venue,'/rewards/testimonial'),['submission_reference'=>'Messenger video','submitted'=>1])->assertSessionHasErrors('testimonial');
        for($i=0;$i<15;$i++) {$this->day($i);PlatformRewards::record($venue,$staff);}
        $this->actingAs($owner)->post($this->url($venue,'/rewards/testimonial'),['submission_reference'=>'Messenger video sent today','submitted'=>1,'marketing_consent'=>1])->assertRedirect();
        $record=DB::table('venue_reward_testimonials')->where('venue_id',$venue->id)->first();
        $this->assertSame('pending',$record->status);
        $this->assertDatabaseCount('venue_reward_grants',0);
        $this->post($this->app('/admin/rewards/testimonials/'.$record->id),['decision'=>'approve','note'=>'Authentic','verified'=>1])->assertForbidden();
        $this->actingAs($admin)->get($this->app('/admin/rewards?tab=testimonials'))->assertOk()->assertSee('Messenger video sent today');
        $this->post($this->app('/admin/rewards/testimonials/'.$record->id),['decision'=>'approve','note'=>'Authentic'])->assertSessionHasErrors('verified');
        $this->post($this->app('/admin/rewards/testimonials/'.$record->id),['decision'=>'reject','note'=>'Please resend the video'])->assertRedirect();
        $this->actingAs($owner)->post($this->url($venue,'/rewards/testimonial'),['submission_reference'=>'Resent video','submitted'=>1])->assertRedirect();
        $this->actingAs($admin)->post($this->app('/admin/rewards/testimonials/'.$record->id),['decision'=>'approve','note'=>'Genuine video, feedback verified','verified'=>1])->assertRedirect();
        $this->assertDatabaseHas('venue_reward_testimonials',['id'=>$record->id,'status'=>'approved','marketing_consent'=>false]);
        $grant=DB::table('venue_reward_grants')->where('venue_id',$venue->id)->first();
        $this->assertSame('testimonial',$grant->milestone);
        $this->post($this->app('/admin/rewards/testimonials/'.$record->id),['decision'=>'approve','note'=>'Repeat','verified'=>1])->assertSessionHasErrors('testimonial');
        $this->get($this->app('/admin/rewards'))->assertOk()->assertSee('Outstanding free months');
        $this->get($this->app('/admin/rewards?tab=streaks'))->assertOk()->assertSee($venue->name);
        $this->get($this->app('/admin/rewards?tab=ledger'))->assertOk()->assertSee('Video testimonial');
        $this->actingAs($admin)->post($this->url($other,'/rewards/'.$grant->id.'/apply'),['confirmed'=>1])->assertNotFound();
        $this->actingAs($owner)->post($this->url($venue,'/rewards/'.$grant->id.'/apply'),['confirmed'=>1])->assertSessionHasErrors('reward');
        $this->assertNull(DB::table('venue_reward_grants')->where('id',$grant->id)->value('applied_at'));
        $this->day(30);
        $this->assertFalse(TrialAccess::allowed($venue));
        $this->actingAs($staff)->post($this->url($venue,'/rewards/'.$grant->id.'/apply'),['confirmed'=>1])->assertForbidden();
        $this->actingAs($owner)->post($this->url($venue,'/rewards/'.$grant->id.'/apply'),['confirmed'=>1])->assertRedirect();
        $renewal=now('Asia/Manila')->addMonthNoOverflow()->toDateString();
        $this->assertDatabaseHas('venue_subscriptions',['venue_id'=>$venue->id,'status'=>'active','renewal_date'=>$renewal,'reward_managed'=>true]);
        $this->assertTrue(TrialAccess::allowed($venue));
        $this->assertDatabaseCount('subscription_payments',0);
        $this->post($this->url($venue,'/rewards/'.$grant->id.'/apply'),['confirmed'=>1])->assertSessionHasErrors('reward');
        $this->actingAs($admin)->post($this->app('/admin/rewards/grants/'.$grant->id.'/revoke'),['reason'=>'Attempted reversal','confirmed'=>1])->assertSessionHasErrors('reward');
        $this->assertDatabaseCount('venue_reward_grants',1);
        $this->patch($this->app('/admin/venues/'.$venue->id.'/billing'),['status'=>'active','renewal_date'=>$renewal])->assertSessionHasErrors('renewal_date');
        $this->travelTo(CarbonImmutable::parse($renewal.' 00:00:00','Asia/Manila'));
        $this->assertFalse(TrialAccess::allowed($venue));
        PlatformRewards::sweep();
        $this->assertDatabaseHas('venue_subscriptions',['venue_id'=>$venue->id,'status'=>'overdue']);
        $this->travelBack();
    }

    public function test_active_renewal_credits_stack_and_unused_revocation_is_once_only(): void
    {
        $this->day(0);
        [$venue,$owner]=$this->venue();
        [$other,$admin]=$this->venue('reward-admin');
        $admin->forceFill(['platform_admin'=>true])->save();
        $original=now('Asia/Manila')->addMonthsNoOverflow(12)->toDateString();
        DB::table('venue_subscriptions')->where('venue_id',$venue->id)->update(['renewal_date'=>$original]);
        foreach(['streak_100'=>1,'streak_300'=>2,'testimonial'=>1] as $milestone=>$months) DB::table('venue_reward_grants')->insert(['venue_id'=>$venue->id,'milestone'=>$milestone,'months'=>$months,'monthly_value'=>100000,'earned_at'=>now()]);
        $grants=DB::table('venue_reward_grants')->where('venue_id',$venue->id)->get()->keyBy('milestone');
        $this->actingAs($admin)->post($this->app('/admin/rewards/grants/'.$grants['testimonial']->id.'/revoke'),['reason'=>'Invalid duplicate video','confirmed'=>1])->assertRedirect();
        $this->actingAs($owner)->post($this->url($venue,'/rewards/'.$grants['testimonial']->id.'/apply'),['confirmed'=>1])->assertSessionHasErrors('reward');
        foreach(['streak_100','streak_300'] as $milestone) $this->post($this->url($venue,'/rewards/'.$grants[$milestone]->id.'/apply'),['confirmed'=>1])->assertRedirect();
        $new=CarbonImmutable::parse($original,'Asia/Manila')->addMonthsNoOverflow(1)->addMonthsNoOverflow(2)->toDateString();
        $this->assertDatabaseHas('venue_subscriptions',['venue_id'=>$venue->id,'renewal_date'=>$new]);
        $this->assertEquals(0,PlatformRewards::summary($venue)['available']);
        $this->assertDatabaseCount('subscription_payments',0);
        $this->travelBack();
    }
}
