<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\URL;

final class SelfService
{
    public static function ready(): bool
    {
        return PlatformEmail::registrationEnabled() && self::mailReady() &&
            LegalPolicies::current()->count() === count(LegalPolicies::SLUGS);
    }

    public static function mailReady(): bool
    {
        return PlatformEmail::ready();
    }

    public static function sendVerification(User $user): bool
    {
        if (!self::mailReady()) return false;
        // Build the link from the canonical management host, never an arbitrary request host.
        $link = URL::temporarySignedRoute('app.verify.complete', now()->addMinutes(60),
            ['id'=>$user->id,'hash'=>sha1($user->email)]);
        return PlatformEmail::send('verification',$user->email,'Verify your Pickle Lite account',
            "Welcome to Pickle Lite.\n\nVerify your email to start your free trial:\n".$link."\n\nThe link expires in 60 minutes. If you did not create this account, ignore this email.");
    }
}
