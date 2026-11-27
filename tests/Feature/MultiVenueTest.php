<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Models\Booking;
use App\Services\VenueInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MultiVenueTest extends TestCase
{
    use RefreshDatabase;

    private Venue $a;
    private Venue $b;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->a = Venue::where('legacy_slot',1)->firstOrFail();
        $this->b = Venue::create(['name'=>'Independent Courts','slug'=>'independent-courts','address'=>'Second venue','phone'=>'','timezone'=>'Asia/Manila','status'=>'active','published'=>true]);
        foreach (range(0,6) as $day) $this->b->hours()->create(['weekday'=>$day,'opens_at'=>'07:00','closes_at'=>'22:00']);
        $this->b->courts()->create(['name'=>'Independent Court','hourly_rate'=>75000,'active'=>true]);
        $this->owner = $this->a->users()->create(['name'=>'Owner A','email'=>'owner-a@example.test','password'=>'owner-password-123','role'=>'owner']);
    }

    private function publicUrl(Venue $venue,string $suffix = ''): string
    {
        return 'http://'.config('pickle.book_host').'/'.$venue->slug.$suffix;
    }

    private function appUrl(string $suffix = ''): string
    {
        return 'http://'.config('pickle.app_host').$suffix;
    }

    private function productUrl(string $suffix = ''): string
    {
        return 'http://'.config('pickle.product_host').$suffix;
    }

    private function workspace(Venue $venue,string $suffix): string
    {
        return $this->appUrl('/venues/'.$venue->slug.$suffix);
    }

    public function test_domains_resolve_independent_venues_and_legacy_links(): void
    {
        $this->get($this->productUrl())->assertOk()->assertSee('More games. Less admin work.')->assertSee('₱1,000')->assertSee('Free setup')->assertDontSee('₱3,000');
        $this->get($this->appUrl())->assertRedirect($this->appUrl('/login'));
        $this->get($this->publicUrl($this->a))->assertOk()->assertSee($this->a->name)->assertDontSee('Independent Courts')->assertSee($this->publicUrl($this->a,'/book'));
        $this->get($this->publicUrl($this->b))->assertOk()->assertSee('Independent Courts')->assertSee('₱750.00')->assertSee($this->publicUrl($this->b,'/book'));
        $this->get($this->publicUrl($this->b,'/book?step=1'))->assertOk()->assertSee('Independent Courts');
        $this->get($this->publicUrl($this->b,'/open-play'))->assertOk()->assertSee('Independent Courts');
        $this->get('http://'.config('pickle.book_host').'/missing-venue')->assertNotFound();
        $this->get('http://'.config('pickle.legacy_host').'/')->assertOk()->assertSee($this->a->name)->assertDontSee('Independent Courts');
        $this->get($this->productUrl('/book'))->assertNotFound();
        $this->get($this->appUrl('/book'))->assertNotFound();
        $this->get($this->publicUrl($this->b,'/settings'))->assertNotFound();
    }

    public function test_booking_and_confirmation_are_scoped_to_the_public_slug(): void
    {
        $this->travelTo(now()->setDate(2030,1,10)->setTime(0,0));
        $courtB = $this->b->courts()->firstOrFail();
        $data = ['court_id'=>$courtB->id,'date'=>'2030-01-11','start'=>'19:00','duration'=>60,'quoted_total'=>75000,'customer_name'=>'Tenant B Player','customer_phone'=>'09171234567'];
        $this->post($this->publicUrl($this->a,'/bookings'),$data)->assertSessionHasErrors('court_id');
        $this->assertDatabaseCount('bookings',0);
        $this->post($this->publicUrl($this->b,'/bookings'),$data)->assertRedirect($this->publicUrl($this->b,'/bookings/confirmed'));
        $record = Booking::firstOrFail();
        $this->assertEquals($courtB->id,$record->court_id);
        $this->get($this->publicUrl($this->a,'/bookings/confirmed'))->assertRedirect($this->publicUrl($this->a,'/book'));
        $this->get($this->publicUrl($this->b,'/bookings/confirmed'))->assertOk()->assertSee(strtoupper($record->reference))->assertSee('Independent Court');
        $this->b->update(['status'=>'suspended','published'=>false]);
        $this->get($this->publicUrl($this->b))->assertNotFound();
        $this->post($this->publicUrl($this->b,'/bookings'),$data)->assertNotFound();
        $this->get($this->publicUrl($this->b,'/bookings/confirmed'))->assertOk();
    }

    public function test_payment_tokens_and_media_are_scoped_to_the_venue(): void
    {
        $this->travelTo(now()->setDate(2030,1,10)->setTime(0,0));
        $this->a->update(['qr_payments_enabled'=>true,
            'payment_qr_base'=>'00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052',
            'payment_messenger_url'=>'https://m.me/litecourts']);
        $data = ['court_id'=>$this->a->courts()->firstOrFail()->id,'date'=>'2030-01-11','start'=>'19:00','duration'=>60,'quoted_total'=>40000,'customer_name'=>'Private Player','customer_phone'=>'09171234567'];
        $this->post($this->publicUrl($this->a,'/bookings'),$data)->assertRedirect();
        $booking = Booking::firstOrFail();
        $path = '/payments/'.$booking->payment_token;
        $this->get($this->publicUrl($this->a,$path))->assertOk()->assertSee($booking->reference);
        $this->get($this->publicUrl($this->b,$path))->assertNotFound();
        $this->getJson($this->publicUrl($this->b,$path.'/status'))->assertNotFound();
        $this->getJson($this->publicUrl($this->a,$path.'/status'))->assertOk()->assertJson(['status'=>'pending']);
        $this->get('http://'.config('pickle.legacy_host').$path)->assertOk()->assertSee($booking->reference);
        $this->actingAs($this->owner);
        $this->owner->venues()->attach($this->b->id,['role'=>'owner']);
        $this->patch($this->workspace($this->b,'/bookings/'.$booking->id.'/cancel'))->assertNotFound();
        $this->assertNull($booking->fresh()->cancelled_at);
        $this->get($this->workspace($this->b,'/calendar/modal/booking/'.$booking->id))->assertNotFound();
    }

    public function test_workspace_roles_and_records_never_follow_a_different_venue(): void
    {
        $this->actingAs($this->owner);
        $this->get($this->appUrl('/venues'))->assertOk()->assertSee($this->a->name)->assertDontSee($this->b->name);
        $this->get($this->workspace($this->b,'/calendar'))->assertForbidden();
        $this->patch($this->workspace($this->b,'/booking-setup/branding'),['brand_color'=>'#ff0000'])->assertForbidden();
        $this->owner->venues()->attach($this->b->id,['role'=>'staff']);
        $this->get($this->workspace($this->a,'/calendar'))->assertOk()->assertSee($this->a->name);
        $this->get($this->workspace($this->b,'/calendar'))->assertOk()->assertSee($this->b->name);
        $this->get($this->workspace($this->b,'/settings'))->assertForbidden();
        $this->patch($this->workspace($this->b,'/booking-setup/branding'),['brand_color'=>'#ff0000'])->assertForbidden();
        $this->patch($this->workspace($this->a,'/booking-setup/branding'),['brand_color'=>'#123456'])->assertRedirect();
        $this->assertEquals('#123456',$this->a->fresh()->brand_color);
        $this->assertNotEquals('#123456',$this->b->fresh()->brand_color);
        $this->owner->venues()->updateExistingPivot($this->b->id,['role'=>'manager']);
        $this->get($this->workspace($this->b,'/website'))->assertOk();
        $this->get($this->workspace($this->b,'/settings'))->assertForbidden();
        $this->owner->venues()->detach($this->b->id);
        $this->get($this->workspace($this->b,'/calendar'))->assertForbidden();
        $this->get($this->appUrl('/admin'))->assertForbidden();
    }

    public function test_admin_can_onboard_with_one_time_invite_and_separate_billing(): void
    {
        $this->actingAs($this->owner);
        $this->post($this->appUrl('/admin/venues'),[])->assertForbidden();
        $this->owner->forceFill(['platform_admin'=>true])->save();
        $this->get($this->appUrl('/admin'))->assertOk();
        $this->post($this->appUrl('/admin/venues'),['name'=>'New Venue','slug'=>'new-venue','timezone'=>'Asia/Manila','owner_email'=>'new-owner@example.test'])
            ->assertRedirect($this->appUrl('/admin'))->assertSessionHas('invite_url');
        $venue = Venue::where('slug','new-venue')->firstOrFail();
        $this->assertFalse($venue->published);
        $inviteUrl = session('invite_url');
        $this->assertDatabaseHas('venue_subscriptions',['venue_id'=>$venue->id,'setup_fee'=>0,'monthly_fee'=>100000]);
        $this->get($this->workspace($venue,'/calendar'))->assertForbidden();
        $this->get($this->publicUrl($venue))->assertNotFound();
        $this->patch($this->appUrl('/admin/venues/'.$venue->id.'/status'),['status'=>'active','published'=>'1'])->assertSessionHasErrors('published');
        $this->post($this->appUrl('/admin/venues/'.$venue->id.'/billing/payments'),['kind'=>'setup','amount'=>'3000.00','reference'=>'TEST-SETUP-001','received_on'=>now()->toDateString()])->assertSessionHasErrors('kind');
        $this->assertDatabaseMissing('subscription_payments',['reference'=>'TEST-SETUP-001']);
        $this->get($this->appUrl('/admin/venues/'.$venue->id.'/billing'))->assertOk()->assertSee('Free setup')->assertDontSee('Setup fee');
        $this->post($this->appUrl('/admin/venues/'.$venue->id.'/billing/payments'),['kind'=>'monthly','amount'=>'1000.00','reference'=>'TEST-MONTHLY-001','received_on'=>now()->toDateString()])->assertRedirect();
        $this->assertDatabaseHas('subscription_payments',['kind'=>'monthly','amount'=>100000,'reference'=>'TEST-MONTHLY-001']);
        $this->assertDatabaseCount('bookings',0);
        $this->post($this->appUrl('/logout'));
        $this->get($inviteUrl)->assertOk()->assertSee('Join New Venue');
        $this->post($inviteUrl,['name'=>'New Owner','password'=>'new-password-123','password_confirmation'=>'new-password-123'])->assertRedirect($this->appUrl('/venues'));
        $this->get($inviteUrl)->assertNotFound();
        $this->get($this->workspace($venue,'/settings'))->assertOk();
        $this->get($this->workspace($this->a,'/settings'))->assertForbidden();
        $this->get($this->appUrl('/admin'))->assertForbidden();
    }

    public function test_product_enquiry_does_not_create_a_venue_or_subscription(): void
    {
        $this->post($this->productUrl('/get-started'),['venue_name'=>'Interested Venue','name'=>'Contact','email'=>'lead@example.test'])->assertRedirect($this->productUrl().'#get-started');
        $this->assertDatabaseHas('product_enquiries',['email'=>'lead@example.test']);
        $this->assertDatabaseCount('venues',2);
    }
}
