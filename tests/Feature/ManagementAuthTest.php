<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Venue;
use App\Services\VenueInvitations;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class ManagementAuthTest extends TestCase
{
    use RefreshDatabase;

    private Venue $venue;
    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->venue = Venue::where('legacy_slot',1)->firstOrFail();
        $this->owner = $this->venue->users()->create([
            'name'=>'Auth test owner','email'=>'auth-owner@example.test','password'=>'sample-password-123','role'=>'owner',
        ]);
    }

    private function url(string $path = ''): string
    {
        return 'http://'.config('pickle.app_host').$path;
    }

    private function calendar(): string
    {
        return $this->url('/venues/'.$this->venue->slug.'/calendar');
    }

    private function signIn(array $extra = [])
    {
        return $this->post($this->url('/login'), ['email'=>$this->owner->email,'password'=>'sample-password-123']+$extra);
    }

    public function test_login_uses_standalone_layout_and_single_venue_redirect(): void
    {
        $this->get($this->url('/login'))->assertOk()->assertSee('Welcome back')
            ->assertSee('/auth.css?v=2',false)->assertSee('Remember me')
            ->assertDontSee('class="site-header"',false)->assertDontSee('Forgot password?')
            ->assertHeader('Cache-Control','no-store, private');
        $this->post($this->url('/login'), ['email'=>$this->owner->email,'password'=>'incorrect'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->signIn()->assertRedirect($this->calendar());
        $this->assertAuthenticatedAs($this->owner);
        $this->assertNull($this->owner->fresh()->remember_token);
        $this->get($this->url('/'))->assertRedirect($this->calendar());
        $this->get($this->url('/login'))->assertRedirect($this->calendar());
        $this->get($this->url('/venues'))->assertOk()->assertSee($this->venue->name);
    }

    public function test_admin_has_platform_destination_even_with_a_venue(): void
    {
        $this->owner->forceFill(['platform_admin'=>true])->save();
        $this->signIn()->assertRedirect($this->url('/admin'));
        $this->get($this->url('/'))->assertRedirect($this->url('/admin'));
        $this->get($this->url('/admin'))->assertOk();
    }

    public function test_multiple_venues_and_no_venue_use_the_selector(): void
    {
        $second = Venue::create(['name'=>'Second Auth Venue','slug'=>'second-auth-venue','address'=>'','phone'=>'','timezone'=>'Asia/Manila']);
        $this->owner->venues()->attach($second->id,['role'=>'staff']);
        $this->signIn()->assertRedirect($this->url('/venues'));
        $this->get($this->url('/venues'))->assertOk()->assertSee($second->name);
        $this->post($this->url('/logout'))->assertRedirect($this->url('/login'));
        $this->owner->venues()->detach();
        $this->signIn()->assertRedirect($this->url('/venues'));
        $this->get($this->url('/venues'))->assertOk()->assertSee('No venue access yet');
        $this->get($this->calendar())->assertForbidden();
    }

    public function test_protected_links_and_invitation_take_priority_but_external_redirect_is_rejected(): void
    {
        $target = $this->url('/venues/'.$this->venue->slug.'/open-play');
        $this->get($target)->assertRedirect($this->url('/login'));
        $this->signIn()->assertRedirect($target);
        $this->post($this->url('/logout'));
        $invite = VenueInvitations::issue($this->venue,$this->owner->email,'staff',$this->owner);
        $this->get($invite)->assertRedirect($this->url('/login'));
        $this->signIn()->assertRedirect($invite);
        $this->get($invite)->assertOk()->assertSee('Join '.$this->venue->name)->assertSee('auth-shell');
        $this->post($this->url('/logout'));
        $this->withSession(['url.intended'=>'https://outside.example.test/venues']);
        $this->signIn()->assertRedirect($this->calendar());
    }

    public function test_remember_me_is_opt_in_and_logout_revokes_token(): void
    {
        $this->signIn(['remember'=>'1'])->assertRedirect($this->calendar())->assertCookie(Auth::guard('web')->getRecallerName());
        $oldToken = $this->owner->fresh()->remember_token;
        $this->assertNotEmpty($oldToken);
        $this->post($this->url('/logout'))->assertRedirect($this->url('/login'));
        $this->assertGuest();
        $this->assertNotSame($oldToken,$this->owner->fresh()->remember_token);
    }

    public function test_recovery_is_disabled_without_explicit_smtp_opt_in(): void
    {
        Notification::fake();
        $this->get($this->url('/forgot-password'))->assertNotFound();
        $this->post($this->url('/forgot-password'),['email'=>$this->owner->email])->assertNotFound();
        config(['pickle.password_reset_enabled'=>true,'mail.default'=>'log']);
        $this->get($this->url('/forgot-password'))->assertNotFound();
        $this->get($this->url('/login'))->assertDontSee('Forgot password?');
        Notification::assertNothingSent();
    }

    public function test_enabled_recovery_uses_single_use_token_generic_response_and_revokes_sessions(): void
    {
        Notification::fake();
        config(['pickle.password_reset_enabled'=>true,'mail.default'=>'smtp']);
        $this->get($this->url('/login'))->assertSee('Forgot password?');
        $this->get($this->url('/forgot-password'))->assertOk();
        $message = 'If that email has an account, we will send a password reset link.';
        $this->post($this->url('/forgot-password'),['email'=>'unknown@example.test'])->assertRedirect($this->url('/forgot-password'))->assertSessionHas('status',$message);
        $this->post($this->url('/forgot-password'),['email'=>$this->owner->email])->assertRedirect($this->url('/forgot-password'))->assertSessionHas('status',$message);
        $token = null;
        Notification::assertSentTo($this->owner, ResetPassword::class, function ($notification) use (&$token) {
            $token = $notification->token;
            $this->assertStringStartsWith($this->url('/reset-password/'), $notification->toMail($this->owner)->actionUrl);
            return true;
        });
        $this->assertNotEmpty($token);
        $this->get($this->url('/reset-password/'.$token).'?email='.urlencode($this->owner->email))->assertOk()->assertSee('Set a new password');
        $this->owner->forceFill(['remember_token'=>'previous-remember-token'])->save();
        DB::table('sessions')->insert(['id'=>'previous-session','user_id'=>$this->owner->id,'payload'=>'test','last_activity'=>time()]);
        $payload = ['token'=>$token,'email'=>$this->owner->email,'password'=>'new-password-456','password_confirmation'=>'new-password-456'];
        $this->post($this->url('/reset-password'),$payload)->assertRedirect($this->url('/login'));
        $this->assertTrue(Hash::check('new-password-456',$this->owner->fresh()->password));
        $this->assertNotSame('previous-remember-token',$this->owner->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions',['id'=>'previous-session']);
        $this->assertDatabaseMissing('password_reset_tokens',['email'=>$this->owner->email]);
        $this->post($this->url('/reset-password'),$payload)->assertSessionHasErrors('email');
        $this->post($this->url('/login'),['email'=>$this->owner->email,'password'=>'new-password-456'])->assertRedirect($this->calendar());
    }
}
