<?php

namespace App\Services;

final class PasswordRecovery
{
    public static function enabled(): bool
    {
        // Never emit live password-reset tokens through Laravel's default log mailer.
        return config('pickle.password_reset_enabled') === true && config('mail.default') === 'smtp';
    }
}
