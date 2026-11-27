<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

final class PlatformEmail
{
    public const ADDRESS = 'picklelite.cloud@gmail.com';
    public const NAME = 'PickleLite App';
    public const HOST = 'smtp.gmail.com';
    public const PORT = 465;

    public static function settings(): ?object
    {
        return Schema::hasTable('platform_email_settings')
            ? DB::table('platform_email_settings')->where('id',1)->first() : null;
    }

    public static function active(?object $settings): bool
    {
        return $settings && $settings->enabled && $settings->verified_at && filled($settings->password_ciphertext);
    }

    public static function ready(): bool
    {
        $settings = self::settings();
        if ($settings) return self::active($settings);
        return config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host')) &&
            filled(config('mail.from.address')) && config('mail.from.address') !== 'hello@example.com';
    }

    public static function registrationEnabled(): bool
    {
        $settings = self::settings();
        return $settings ? self::active($settings) && $settings->registration_enabled : config('selfservice.enabled');
    }

    public static function recoveryEnabled(): bool
    {
        $settings = self::settings();
        return $settings ? self::active($settings) && $settings->recovery_enabled :
            config('pickle.password_reset_enabled') === true && config('mail.default') === 'smtp';
    }

    // The fixed provider and implicit TLS avoid arbitrary SMTP endpoints, TLS
    // downgrade, and a persistent global mailer configuration in workers.
    public static function send(string $kind, string $recipient, string $subject, string $body, ?object $testSettings = null): bool
    {
        $settings = $testSettings ?? self::settings();
        if (!$testSettings && ($settings ? !self::active($settings) : !self::ready())) return false;
        try {
            if ($settings) {
                $mailer = Mail::build([
                    'transport'=>'smtp', 'scheme'=>'smtps', 'host'=>self::HOST, 'port'=>self::PORT,
                    'username'=>self::ADDRESS, 'password'=>Crypt::decryptString($settings->password_ciphertext),
                    'timeout'=>10, 'verify_peer'=>true,
                ]);
                $mailer->raw($body, fn ($message) => $message->from(self::ADDRESS,self::NAME)
                    ->replyTo(self::ADDRESS,self::NAME)->to($recipient)->subject($subject));
            } else {
                Mail::raw($body, fn ($message) => $message->to($recipient)->subject($subject));
            }
        } catch (\Throwable $e) {
            // Do not log transport exception details, recipients, message bodies,
            // authentication links or SMTP credentials.
            Log::warning('Platform email submission failed.', ['kind'=>$kind,'reason'=>self::failureReason($e)]);
            self::record($kind,'failed');
            return false;
        }
        self::record($kind,'submitted');
        return true;
    }

    private static function failureReason(\Throwable $error): string
    {
        if ($error instanceof \Illuminate\Contracts\Encryption\DecryptException) return 'credentials_unreadable';
        if (!$error instanceof \Symfony\Component\Mailer\Exception\TransportExceptionInterface) return 'configuration_or_internal';
        // Match internally, emit only fixed diagnostic labels, never the raw error.
        $message = strtolower($error->getMessage());
        $code = (int)$error->getCode();
        if (in_array($code,[530,534,535],true) || str_contains($message,'authenticate') || str_contains($message,'authentication')) return 'smtp_authentication_rejected';
        if (str_contains($message,'timed out') || str_contains($message,'timeout')) return 'smtp_timeout';
        if (str_contains($message,'certificate') || str_contains($message,'tls') || str_contains($message,'ssl')) return 'smtp_tls_failed';
        if (str_contains($message,'connect') || str_contains($message,'network') || str_contains($message,'getaddrinfo')) return 'smtp_connection_failed';
        if ($code >= 400 && $code <= 599) return 'smtp_rejected_'.$code;
        return 'smtp_transport_failed';
    }

    public static function record(string $kind, string $status): void
    {
        try {
            if (Schema::hasTable('platform_email_logs')) DB::table('platform_email_logs')->insert([
                'kind'=>$kind,'status'=>$status,'created_at'=>now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Platform email audit record could not be saved.');
        }
    }
}
