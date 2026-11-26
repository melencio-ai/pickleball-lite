<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentReferences
{
    // Call inside the same transaction that marks the booking or registration paid.
    public static function claim(string $reference): string
    {
        $reference = strtoupper($reference);
        if (DB::table('bookings')->where('payment_reference', $reference)->exists()
            || DB::table('open_play_registrations')->where('payment_reference', $reference)->exists()) {
            throw ValidationException::withMessages(['payment_reference' => 'That transaction reference is already recorded.']);
        }
        try {
            DB::table('payment_references')->insert(['reference' => $reference]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['payment_reference' => 'That transaction reference is already recorded.']);
        }

        return $reference;
    }
}
