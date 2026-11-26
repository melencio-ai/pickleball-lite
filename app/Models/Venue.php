<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    public const BOOKING_DURATIONS = [60, 90, 120, 150, 180, 210, 240, 270, 300];

    protected $fillable = [
        'name', 'address', 'phone', 'timezone', 'brand_color', 'map_url', 'messenger_url',
        'booking_durations', 'advance_days', 'minimum_notice', 'cancellation_policy',
        'qr_payments_enabled', 'payment_qr_base', 'payment_messenger_url',
    ];

    protected $casts = [
        'booking_durations' => 'array', 'advance_days' => 'integer', 'minimum_notice' => 'integer', 'qr_payments_enabled' => 'boolean',
    ];

    public function durations(): array
    {
        $configured = array_map('intval', $this->booking_durations ?? self::BOOKING_DURATIONS);
        $allowed = array_values(array_intersect(self::BOOKING_DURATIONS, $configured));

        return $allowed ?: self::BOOKING_DURATIONS;
    }

    public static function durationLabel(int $minutes): string
    {
        $hours = intdiv($minutes, 60);
        $label = $hours.' '.($hours === 1 ? 'hour' : 'hours');

        return $minutes % 60 === 0 ? $label : $label.' and 30 minutes';
    }

    public function defaultDuration(): int
    {
        return in_array(60, $this->durations(), true) ? 60 : $this->durations()[0];
    }

    public function openPlaySessions(): HasMany
    {
        return $this->hasMany(OpenPlaySession::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(VenueMedia::class);
    }

    public function closures(): HasMany
    {
        return $this->hasMany(VenueClosure::class);
    }

    public function rates(): HasMany
    {
        return $this->hasMany(VenueRate::class);
    }

    public function courts(): HasMany
    {
        return $this->hasMany(Court::class);
    }

    public function hours(): HasMany
    {
        return $this->hasMany(VenueHour::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
