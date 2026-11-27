<?php

namespace Tests\Feature;

use App\Http\Controllers\LegalAdminController;
use App\Models\Booking;
use App\Models\OpenPlayRegistration;
use App\Models\User;
use App\Models\Venue;
use App\Services\LegalPolicies;
use App\Services\OpenPlay;
use App\Services\VenueInvitations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegalPoliciesTest extends TestCase
{
    use RefreshDatabase;
    private Venue $venue;
    private Venue $other;
    private User $owner;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->venue = Venue::where('legacy_slot',1)->firstOrFail();
        $this->venue->update(['cancellation_policy'=>'Original venue cancellation terms.']);
        $this->other = Venue::create(['name'=>'Another legal venue','slug'=>'another-legal-venue','address'=>'','phone'=>'','timezone'=>'Asia/Manila','status'=>'active','published'=>true]);
        $this->owner = $this->venue->users()->create(['name'=>'Legal Owner','email'=>'legal-owner@example.test','password'=>'password-123456','role'=>'owner']);
        $this->admin = $this->venue->users()->create(['name'=>'Legal Admin','email'=>'legal-admin@example.test','password'=>'password-123456','role'=>'owner']);
        $this->admin->forceFill(['platform_admin'=>true])->save();
    }

    private function appUrl(string $path): string { return 'http://'.config('pickle.app_host').$path; }
    private function webUrl(string $path): string { return 'http://'.config('pickle.product_host').$path; }
    private function bookUrl(string $path = ''): string { return 'http://'.config('pickle.book_host').'/'.$this->venue->slug.$path; }
    private function workspace(string $path): string { return $this->appUrl('/venues/'.$this->venue->slug.$path); }

    private function publication(array $override = []): array
    {
        return $override + [
            'legal_name'=>'Example Test Operator','business_address'=>'Example test address',
            'support_email'=>'support@example.test','privacy_email'=>'privacy@example.test',
            'retention'=>'Test-only retention schedule for all records.',
            'commercial_conditions'=>'Test-only commercial conditions.',
            'confirmed'=>'1','draft_fingerprint'=>LegalAdminController::draftFingerprint(DB::table('legal_documents')->orderBy('slug')->get()),
        ];
    }

    private function publish(): void
    {
        $this->actingAs($this->admin)->post($this->appUrl('/admin/legal/publish'),$this->publication())
            ->assertRedirect($this->appUrl('/accept-terms'));
        $this->acceptCurrent();
    }

    private function acceptCurrent(): void
    {
        $ctx = LegalPolicies::context();
        $this->post($this->appUrl('/accept-terms'),['legal_accepted'=>'1','legal_fingerprint'=>$ctx['fingerprint']])->assertRedirect();
    }

    private function bookingData(string $start='19:00'): array
    {
        return ['court_id'=>$this->venue->courts()->firstOrFail()->id,'date'=>'2030-01-11','start'=>$start,'duration'=>60,
            'quoted_total'=>40000,'customer_name'=>'Legal test player','customer_phone'=>'09171234567'];
    }

    public function test_drafts_are_private_publication_requires_review_and_old_versions_are_immutable(): void
    {
        $this->get($this->webUrl('/terms'))->assertOk()->assertSee('Policy not yet published')->assertDontSee('Accounts and eligibility');
        $this->get($this->appUrl('/admin/legal'))->assertRedirect($this->appUrl('/login'));
        $this->actingAs($this->owner)->get($this->appUrl('/admin/legal'))->assertForbidden();
        $this->actingAs($this->admin)->get($this->appUrl('/admin/legal'))->assertOk()->assertSee('Draft mode');
        $this->post($this->appUrl('/admin/legal/publish'),$this->publication(['legal_name'=>'']))->assertSessionHasErrors('legal_name');
        $this->post($this->appUrl('/admin/legal/publish'),$this->publication(['confirmed'=>'0']))->assertSessionHasErrors('confirmed');
        $this->assertDatabaseCount('legal_document_versions',0);
        $this->publish();
        $original = LegalPolicies::current()->get('terms');
        $this->get($this->webUrl('/terms'))->assertOk()->assertSee('Example Test Operator')->assertSee('Version 1');
        $this->get($this->webUrl('/privacy'))->assertOk()->assertSee('Test-only retention schedule');
        $this->patch($this->appUrl('/admin/legal/terms'),['body'=>'Revised terms text.'])->assertRedirect();
        $this->get($this->webUrl('/terms'))->assertOk()->assertDontSee('Revised terms text.');
        $this->post($this->appUrl('/admin/legal/publish'),$this->publication(['draft_fingerprint'=>str_repeat('0',64)]))->assertSessionHasErrors('draft_fingerprint');
        $this->publish();
        $this->get($this->webUrl('/terms'))->assertOk()->assertSee('Revised terms text.')->assertSee('Version 2');
        $this->get($this->webUrl('/terms?version=1'))->assertOk()->assertSee('archived version')->assertDontSee('Revised terms text.');
        $this->assertSame($original->body,DB::table('legal_document_versions')->where('id',$original->id)->value('body'));
        $this->assertDatabaseCount('legal_document_versions',8);
    }

    public function test_booking_terms_are_server_enforced_and_policy_changes_do_not_rewrite_receipts(): void
    {
        $this->travelTo(now()->setDate(2030,1,10)->setTime(0,0));
        $this->publish();
        Auth::logout();
        $ctx = LegalPolicies::context($this->venue->fresh());
        $url = $this->bookUrl('/bookings');
        $data = $this->bookingData();
        $this->get($this->bookUrl('/policies'))->assertOk()->assertSee('Original venue cancellation terms.');
        $this->post($url,$data)->assertSessionHasErrors('legal_accepted');
        $this->post($url,$data+['legal_fingerprint'=>$ctx['fingerprint']])->assertSessionHasErrors('legal_accepted');
        $this->assertDatabaseCount('bookings',0);
        $this->venue->update(['cancellation_policy'=>'Changed venue cancellation terms.']);
        $this->post($url,$data+['legal_fingerprint'=>$ctx['fingerprint'],'legal_accepted'=>'1'])->assertSessionHasErrors('legal_accepted');
        $ctx = LegalPolicies::context($this->venue->fresh());
        $this->post($url,$data+['legal_fingerprint'=>$ctx['fingerprint'],'legal_accepted'=>'1'])->assertRedirect($this->bookUrl('/bookings/confirmed'));
        $booking = Booking::firstOrFail();
        $this->assertSame('Changed venue cancellation terms.',$booking->cancellation_policy);
        $this->assertDatabaseHas('legal_acceptances',['subject_type'=>'booking','subject_id'=>$booking->id,'venue_id'=>$this->venue->id,'action'=>'accepted','legal_document_version_id'=>$ctx['terms']->id]);
        $this->assertDatabaseHas('legal_acceptances',['subject_type'=>'booking','subject_id'=>$booking->id,'action'=>'notice_presented','legal_document_version_id'=>$ctx['privacy']->id]);
        $this->venue->update(['cancellation_policy'=>'Third cancellation policy.']);
        $this->get($this->bookUrl('/bookings/confirmed'))->assertOk()->assertSee('Changed venue cancellation terms.')->assertSee('Terms of Use (v1)')->assertDontSee('Third cancellation policy.');
        $this->venue->update(['published'=>false,'status'=>'suspended']);
        $this->get($this->bookUrl('/bookings/confirmed'))->assertOk();
    }

    public function test_open_play_snapshot_and_existing_payment_link_survive_policy_updates(): void
    {
        $this->travelTo(now()->setDate(2030,1,10)->setTime(0,0));
        $this->venue->update(['qr_payments_enabled'=>true,
            'payment_qr_base'=>'00020101021126320014PH.PPMI.TESTQR0110TESTVENUE15204479953036085802PH5910LITE COURT6006MANILA63041052',
            'payment_messenger_url'=>'https://m.me/litecourts']);
        $this->publish();
        Auth::logout();
        $session = app(OpenPlay::class)->create($this->venue,['name'=>'Legal Open Play','date'=>'2030-01-11','start'=>'15:00','end'=>'16:00',
            'court_ids'=>[$this->venue->courts()->firstOrFail()->id],'capacity'=>8,'price'=>'200.00','skill_level'=>'all','repeat'=>'none'])[0];
        $url = $this->bookUrl('/open-play/'.$session->id.'/join');
        $data = ['customer_name'=>'Open Play Legal','customer_phone'=>'09170000000','quoted_total'=>20000];
        $this->post($url,$data)->assertSessionHasErrors('legal_accepted');
        $ctx = LegalPolicies::context($this->venue->fresh());
        $this->post($url,$data+['legal_fingerprint'=>$ctx['fingerprint'],'legal_accepted'=>'1'])->assertRedirect();
        $registration = OpenPlayRegistration::firstOrFail();
        $this->assertSame('Original venue cancellation terms.',$registration->cancellation_policy);
        $this->assertDatabaseHas('legal_acceptances',['subject_type'=>'open_play','subject_id'=>$registration->id,'venue_id'=>$this->venue->id,'action'=>'accepted']);
        $this->venue->update(['cancellation_policy'=>'New venue conditions.']);
        $this->get($this->bookUrl('/open-play/payments/'.$registration->payment_token))->assertOk()->assertSee('Original venue cancellation terms.')->assertSee('Terms of Use (v1)')->assertDontSee('New venue conditions.');
    }

    public function test_updated_terms_gate_management_and_owner_invitation_requires_subscription_terms(): void
    {
        $this->publish();
        $this->actingAs($this->owner)->get($this->workspace('/calendar'))->assertRedirect($this->appUrl('/accept-terms'));
        $this->get($this->appUrl('/accept-terms'))->assertOk()->assertSee('Accept and continue');
        $this->post($this->appUrl('/accept-terms'),[])->assertSessionHasErrors('legal_accepted');
        $this->acceptCurrent();
        $this->get($this->workspace('/calendar'))->assertOk();
        $this->actingAs($this->admin);
        $invite = VenueInvitations::issue($this->other,'legal-new-owner@example.test','owner',$this->admin);
        Auth::logout();
        $this->get($invite)->assertOk()->assertSee('venue subscription terms');
        $ctx = LegalPolicies::context(subscription:true);
        $data = ['name'=>'New Legal Owner','password'=>'new-password-123','password_confirmation'=>'new-password-123','legal_fingerprint'=>$ctx['fingerprint'],'legal_accepted'=>'1'];
        $this->post($invite,$data)->assertSessionHasErrors('subscription_accepted');
        $this->assertDatabaseMissing('users',['email'=>'legal-new-owner@example.test']);
        $this->post($invite,$data+['subscription_accepted'=>'1'])->assertRedirect($this->appUrl('/venues'));
        $newOwner = User::where('email','legal-new-owner@example.test')->firstOrFail();
        $this->assertDatabaseHas('legal_acceptances',['subject_type'=>'venue_subscription','subject_id'=>$this->other->id,'venue_id'=>$this->other->id,'accepted_by'=>$newOwner->id,'legal_document_version_id'=>$ctx['subscriptionVersion']->id]);
        $this->assertDatabaseHas('legal_acceptances',['subject_type'=>'user','subject_id'=>$newOwner->id,'action'=>'accepted']);
    }

    public function test_privacy_requests_are_venue_scoped_and_manual(): void
    {
        $data = ['kind'=>'access','contact'=>'Internal test case','received_on'=>now()->toDateString()];
        $this->actingAs($this->admin)->post($this->appUrl('/admin/privacy-requests'),$data+['venue_id'=>$this->other->id])->assertRedirect();
        $foreign = DB::table('privacy_requests')->first();
        $this->actingAs($this->owner)->get($this->workspace('/privacy-requests'))->assertOk()->assertDontSee('Internal test case');
        $this->patch($this->workspace('/privacy-requests/'.$foreign->id),['status'=>'resolved'])->assertNotFound();
        $this->post($this->workspace('/privacy-requests'),$data+['venue_id'=>$this->other->id])->assertSessionHasErrors('venue_id');
        $this->post($this->workspace('/privacy-requests'),$data+['contact'=>'Own test case'])->assertRedirect();
        $own = DB::table('privacy_requests')->where('venue_id',$this->venue->id)->first();
        $this->patch($this->workspace('/privacy-requests/'.$own->id),['status'=>'resolved'])->assertRedirect();
        $this->assertDatabaseHas('privacy_requests',['id'=>$own->id,'status'=>'resolved']);
        $this->assertDatabaseHas('privacy_requests',['id'=>$foreign->id,'status'=>'received']);
        $this->assertDatabaseHas('users',['id'=>$this->owner->id]);
    }
}
