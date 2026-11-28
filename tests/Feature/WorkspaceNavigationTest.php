<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function appUrl(string $path): string {return 'http://'.config('pickle.app_host').$path;}
    private function venueUrl(Venue $venue,string $path): string {return $this->appUrl('/venues/'.$venue->slug.$path);}

    public function test_grouped_navigation_uses_current_venue_role_and_keeps_platform_links_separate(): void
    {
        $this->seed();
        $a=Venue::firstOrFail();
        $b=Venue::create(['name'=>'Second Assigned Venue','slug'=>'second-assigned-venue','address'=>'Test address','phone'=>'','timezone'=>'Asia/Manila','published'=>true,'status'=>'active']);
        $c=Venue::create(['name'=>'Unassigned Hidden Venue','slug'=>'unassigned-hidden-venue','address'=>'Test address','phone'=>'','timezone'=>'Asia/Manila','published'=>true,'status'=>'active']);
        $owner=$a->users()->create(['name'=>'Owner','email'=>'nav-owner@example.test','password'=>'test-password-123','role'=>'owner']);
        $owner->venues()->attach($b->id,['role'=>'manager']);
        $staff=$a->users()->create(['name'=>'Staff','email'=>'nav-staff@example.test','password'=>'test-password-123','role'=>'staff']);
        DB::table('venue_subscriptions')->where('venue_id',$a->id)->update(['status'=>'active','renewal_date'=>now('Asia/Manila')->addMonth()->toDateString()]);
        config(['pickle.demo_slug'=>null]);
        $this->actingAs($owner)->get($this->venueUrl($a,'/calendar'))->assertOk()
            ->assertSee('data-nav-context="venue"',false)->assertSee('data-platform-brand="mark"',false)
            ->assertSee('data-mobile-tab="calendar"',false)->assertSee('data-mobile-tab="open-play"',false)
            ->assertSee('data-mobile-tab="alerts"',false)->assertSee('data-mobile-more',false)
            ->assertDontSee('data-mobile-tab="platform-overview"',false)
            ->assertSee('aria-label="Operations"',false)->assertSee('aria-label="Online booking"',false)
            ->assertSee('aria-label="Membership"',false)->assertSee('aria-label="Administration"',false)
            ->assertSee('data-nav-item="calendar"',false)->assertSee('data-nav-item="open-play"',false)
            ->assertSee('data-nav-item="website-settings"',false)->assertSee('data-nav-item="booking-setup"',false)
            ->assertSee('data-nav-item="venue-privacy"',false)->assertSee('data-nav-item="venue-settings"',false)
            ->assertSee('data-nav-item="subscription"',false)
            ->assertSee('data-workspace-choice="'.$b->slug.'"',false)
            ->assertDontSee('data-workspace-choice="'.$c->slug.'"',false)
            ->assertDontSee('data-workspace-choice="platform"',false)
            ->assertDontSee('data-nav-item="platform-overview"',false);
        $this->get($this->venueUrl($a,'/booking-setup'))->assertOk()->assertSee('data-nav-item="booking-setup"',false);
        $this->get($this->venueUrl($b,'/calendar'))->assertOk()->assertSee('data-nav-item="website-settings"',false)
            ->assertDontSee('data-nav-item="venue-settings"',false)->assertDontSee('data-nav-item="booking-setup"',false)
            ->assertDontSee('data-nav-item="subscription"',false)->assertDontSee('data-nav-item="rewards"',false)
            ->assertDontSee('aria-label="Membership"',false)->assertDontSee('aria-label="Administration"',false);
        $this->get($this->venueUrl($b,'/booking-setup'))->assertForbidden();
        $this->get($this->venueUrl($c,'/calendar'))->assertForbidden();
        $this->actingAs($staff)->get($this->venueUrl($a,'/calendar'))->assertOk()
            ->assertSee('data-nav-item="calendar"',false)->assertSee('data-nav-item="open-play"',false)
            ->assertSee('data-nav-item="website"',false)->assertDontSee('data-nav-item="website-settings"',false)
            ->assertDontSee('data-nav-item="booking-setup"',false)->assertDontSee('data-nav-item="venue-privacy"',false)
            ->assertDontSee('data-nav-item="subscription"',false)->assertDontSee('data-workspace-choice="'.$b->slug.'"',false);
        $this->get($this->appUrl('/admin'))->assertForbidden();
        $owner->forceFill(['platform_admin'=>true])->save();
        $this->actingAs($owner)->get($this->appUrl('/admin'))->assertOk()
            ->assertSee('data-nav-context="platform"',false)->assertSee('data-platform-brand="primary"',false)->assertSee('data-nav-item="platform-overview"',false)
            ->assertSee('data-mobile-tab="platform-overview"',false)->assertSee('data-mobile-tab="platform-rewards"',false)
            ->assertDontSee('data-mobile-tab="calendar"',false)
            ->assertSee('data-nav-item="platform-rewards"',false)->assertSee('data-nav-item="platform-email"',false)
            ->assertSee('data-nav-item="platform-legal"',false)->assertSee('data-nav-item="platform-privacy"',false)
            ->assertDontSee('data-nav-item="calendar"',false)->assertDontSee('data-nav-item="venue-settings"',false)
            ->assertSee('data-workspace-choice="platform"',false)->assertSee('data-workspace-choice="'.$a->slug.'"',false)
            ->assertDontSee('data-workspace-choice="'.$c->slug.'"',false);
        $this->get($this->venueUrl($a,'/calendar'))->assertOk()->assertSee('data-nav-context="venue"',false)
            ->assertSee('data-workspace-choice="platform"',false)->assertDontSee('data-nav-item="platform-overview"',false);
        $this->get($this->appUrl('/venues'))->assertOk()->assertSee('data-nav-context="account"',false)
            ->assertSee('data-mobile-tab="my-venues"',false)->assertDontSee('data-mobile-tab="calendar"',false)
            ->assertSee('data-nav-item="my-venues"',false)->assertDontSee('data-nav-item="calendar"',false);
        $this->get($this->appUrl('/admin/venues/'.$a->id.'/billing'))->assertOk()->assertSee('data-nav-item="platform-overview"',false);
    }
}
