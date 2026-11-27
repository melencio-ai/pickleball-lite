<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Services\PlatformEmail;
use App\Services\PasswordRecovery;
use App\Services\SelfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PlatformEmailTest extends TestCase
{
    use RefreshDatabase;

    private function url(string $path='/admin/email'): string
    {
        return 'http://'.config('pickle.app_host').$path;
    }

    private function users(): array
    {
        $this->seed();
        $venue = Venue::where('legacy_slot',1)->firstOrFail();
        $admin = $venue->users()->create(['name'=>'Platform admin','email'=>'admin@example.test','password'=>'test-password-123','role'=>'owner']);
        $admin->forceFill(['platform_admin'=>true])->save();
        $owner = $venue->users()->create(['name'=>'Venue owner','email'=>'owner@example.test','password'=>'test-password-123','role'=>'owner']);
        return [$admin,$owner];
    }

    private function mockSend(string $contains, bool $failure=false, int $times=1): void
    {
        $mailer = \Mockery::mock(Mailer::class);
        $expect = $mailer->shouldReceive('raw')->times($times)->withArgs(fn ($body,$callback)=>str_contains($body,$contains) && is_callable($callback));
        if ($failure) $expect->andThrow(new \RuntimeException('sensitive fake SMTP error'));
        else $expect->andReturnNull();
        Mail::shouldReceive('build')->times($times)->withArgs(fn ($config) =>
            $config['host'] === 'smtp.gmail.com' && $config['port'] === 465 &&
            $config['scheme'] === 'smtps' && $config['username'] === PlatformEmail::ADDRESS &&
            $config['password'] === 'abcdefghijklmnop' && $config['verify_peer'] === true
        )->andReturn($mailer);
    }

    public function test_only_platform_admin_can_configure_and_password_is_encrypted(): void
    {
        [$admin,$owner]=$this->users();
        $this->actingAs($owner)->get($this->url())->assertForbidden();
        $this->patch($this->url(),['password'=>'abcdefghijklmnop'])->assertForbidden();
        $this->actingAs($admin)->get($this->url())->assertOk()->assertSee('picklelite.cloud@gmail.com');
        $this->patch($this->url(),['password'=>'not-an-app-password'])->assertSessionHasErrors('password');
        $this->assertDatabaseCount('platform_email_settings',0);
        $this->patch($this->url(),['password'=>'abcd efgh ijkl mnop'])->assertRedirect();
        $record=PlatformEmail::settings();
        $this->assertSame('abcdefghijklmnop',Crypt::decryptString($record->password_ciphertext));
        $this->assertNotSame('abcdefghijklmnop',$record->password_ciphertext);
        $this->assertFalse(PlatformEmail::ready());
        $this->get($this->url())->assertOk()->assertDontSee('abcdefghijklmnop')->assertDontSee($record->password_ciphertext);
        $this->patch($this->url('/admin/email/activation'),['enabled'=>1,'received'=>1])->assertSessionHasErrors('email');
    }

    public function test_successful_test_and_confirmed_receipt_enable_registration_and_recovery(): void
    {
        [$admin,$owner]=$this->users();
        $this->actingAs($admin)->patch($this->url(),['password'=>'abcdefghijklmnop'])->assertRedirect();
        $this->mockSend('test message');
        $this->post($this->url('/admin/email/test'))->assertRedirect();
        $this->assertDatabaseHas('platform_email_logs',['kind'=>'test','status'=>'submitted']);
        $this->assertNotNull(PlatformEmail::settings()->verified_at);
        $this->patch($this->url('/admin/email/activation'),['enabled'=>1])->assertSessionHasErrors('email');
        $this->patch($this->url('/admin/email/activation'),['enabled'=>1,'received'=>1,'registration'=>1,'recovery'=>1])->assertRedirect();
        $this->assertTrue(PlatformEmail::ready());
        $this->assertTrue(PlatformEmail::registrationEnabled());
        $this->assertTrue(PasswordRecovery::enabled());
        $this->assertFalse(SelfService::ready()); // Legal documents are still unpublished.
        $this->mockSend('Verify your email');
        $this->assertTrue(SelfService::sendVerification($owner));
        $this->assertDatabaseHas('platform_email_logs',['kind'=>'verification','status'=>'submitted']);
        $this->patch($this->url(),['password'=>'qrstuvwxyzabcdef'])->assertRedirect();
        $this->assertFalse(PlatformEmail::ready());
        $this->assertFalse(PasswordRecovery::enabled());
        $this->assertNull(PlatformEmail::settings()->verified_at);
    }

    public function test_failed_test_does_not_allow_activation_or_expose_transport_errors(): void
    {
        [$admin]=$this->users();
        $this->actingAs($admin)->patch($this->url(),['password'=>'abcdefghijklmnop'])->assertRedirect();
        $this->mockSend('test message',true);
        $this->post($this->url('/admin/email/test'))->assertSessionHasErrors('email');
        $this->assertNull(PlatformEmail::settings()->verified_at);
        $this->assertDatabaseHas('platform_email_logs',['kind'=>'test','status'=>'failed']);
        $this->get($this->url())->assertOk()->assertDontSee('sensitive fake SMTP error');
    }

    public function test_polling_and_save_do_not_consume_test_limit_and_excess_requests_return_to_settings(): void
    {
        [$admin]=$this->users();
        $this->actingAs($admin);
        for ($i=0;$i<12;$i++) $this->getJson($this->url('/notifications/count'))->assertOk();
        $this->patch($this->url(),['password'=>'abcdefghijklmnop'])->assertRedirect();
        $this->mockSend('test message',false,5);
        for ($i=0;$i<5;$i++) {
            $this->post($this->url('/admin/email/test'))->assertRedirect();
            $this->getJson($this->url('/notifications/count'))->assertOk();
        }
        $this->post($this->url('/admin/email/test'))->assertStatus(303)
            ->assertRedirect($this->url())->assertHeader('Retry-After')
            ->assertSessionHas('email_rate_limit.action','test');
        $this->get($this->url())->assertOk()->assertSee('data-email-rate-limit="test"',false);
        $this->postJson($this->url('/admin/email/test'))->assertTooManyRequests()->assertJsonStructure(['message','retry_after']);
        $this->assertDatabaseCount('platform_email_logs',5);
        $this->patch($this->url('/admin/email/activation'),['enabled'=>1,'received'=>1])->assertRedirect();
        $this->assertTrue(PlatformEmail::ready());
        $this->getJson($this->url('/notifications/count'))->assertOk();
    }

    public function test_guest_password_recovery_throttle_does_not_block_registration(): void
    {
        $this->users();
        for ($i=0;$i<5;$i++) $this->post($this->url('/forgot-password'),['email'=>'unknown@example.test'])->assertNotFound();
        $this->post($this->url('/forgot-password'),['email'=>'unknown@example.test'])->assertTooManyRequests();
        $this->post($this->url('/register'),[])->assertNotFound();
    }

    public function test_managed_password_recovery_uses_broker_and_generic_response(): void
    {
        [$admin,$owner]=$this->users();
        DB::table('platform_email_settings')->insert([
            'id'=>1,'password_ciphertext'=>Crypt::encryptString('abcdefghijklmnop'),'enabled'=>true,
            'registration_enabled'=>false,'recovery_enabled'=>true,'revision'=>1,'verified_at'=>now(),
            'created_at'=>now(),'updated_at'=>now(),
        ]);
        $this->mockSend('Reset your password:');
        $this->post($this->url('/forgot-password'),['email'=>$owner->email])
            ->assertRedirect($this->url('/forgot-password'))
            ->assertSessionHas('status','If that email has an account, we will send a password reset link.');
        $this->assertDatabaseHas('password_reset_tokens',['email'=>$owner->email]);
        $this->assertDatabaseHas('platform_email_logs',['kind'=>'password_reset','status'=>'submitted']);
        $this->post($this->url('/forgot-password'),['email'=>'unknown@example.test'])
            ->assertRedirect($this->url('/forgot-password'))
            ->assertSessionHas('status','If that email has an account, we will send a password reset link.');
        $this->assertDatabaseCount('platform_email_logs',1);
    }
}
