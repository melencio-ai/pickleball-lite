<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Enable the new one-to-five-hour defaults on existing installations.
        // Owners can choose a smaller subset again in Booking setup.
        DB::table('venues')->whereNotNull('booking_durations')->update([
            'booking_durations' => null,
        ]);
    }

    public function down(): void
    {
        // Previous custom selections cannot be reconstructed.
        // Null also uses the default range on earlier app versions.
    }
};
