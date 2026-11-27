<?php

namespace Database\Seeders;

use App\Models\Venue;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (Venue::query()->exists()) {
            return;
        }

        $venue = Venue::create([
            'name' => 'Pickleball Lite Courts',
            'slug' => 'pickleball-lite-courts', 'legacy_slot' => 1,
            'address' => '',
            'phone' => '',
            'timezone' => 'Asia/Manila',
        ]);

        foreach (range(0, 6) as $day) {
            $venue->hours()->create(['weekday' => $day, 'opens_at' => '07:00', 'closes_at' => '22:00']);
        }

        foreach (range(1, 3) as $number) {
            $venue->courts()->create(['name' => "Court $number", 'hourly_rate' => 40000]);
        }
    }
}
