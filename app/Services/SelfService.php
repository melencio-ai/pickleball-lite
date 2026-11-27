<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

final class SelfService
{
    public static function ready(): bool
    {
        return config('selfservice.enabled') && self::mailReady() &&
            LegalPolicies::current()->count() === count(LegalPolicies::SLUGS);
    }

    public static function mailReady(): bool
    {
        return config('mail.default') === 'smtp' && filled(config('mail.mailers.smtp.host')) &&
            filled(config('mail.from.address')) && config('mail.from.address') !== 'hello@example.com';
    }

    public static function sendVerification(User $user): bool
    {
        if (!self::mailReady()) return false;
        // Build the link from the canonical management host, never an arbitrary request host.
        $link = URL::temporarySignedRoute('app.verify.complete', now()->addMinutes(60),
            ['id'=>$user->id,'hash'=>sha1($user->email)]);
        try {
            Mail::raw("Welcome to Pickle Lite.\n\nVerify your email to start your free trial:\n".$link."\n\nThe link expires in 60 minutes. If you did not create this account, ignore this email.",
                fn($message)=>$message->to($user->email)->subject('Verify your Pickle Lite account'));
            return true;
        } catch (\Throwable $e) {
            Log::warning('Self-service email verification delivery failed.', ['exception'=>get_class($e)]);
            return false;
        }
    }
}
