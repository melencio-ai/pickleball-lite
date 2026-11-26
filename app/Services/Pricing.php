<?php

namespace App\Services;

use App\Models\Court;
use App\Models\Venue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class Pricing
{
    public const PESOS = '/^[0-9]{1,6}(?:\.[0-9]{1,2})?$/D';

    public static function centavos(string $amount): int
    {
        [$pesos, $centavos] = array_pad(explode('.', $amount, 2), 2, '');

        return (int) $pesos * 100 + (int) str_pad($centavos, 2, '0');
    }

    public function dailyRates(Venue $venue, string $date): Collection
    {
        $weekday = CarbonImmutable::parse($date, $venue->timezone)->dayOfWeek;

        return $venue->rates()->get()
            ->filter(fn ($rate) => in_array($weekday, $rate->weekdays, true))
            ->sortBy(fn ($rate) => $rate->court_id === null);
    }

    public function hourlyRate(Court $court, string $time, Collection $rates): int
    {
        $rate = $rates->first(fn ($rate) => ($rate->court_id === null || $rate->court_id === $court->id)
            && $rate->starts_at <= $time && $rate->ends_at > $time);

        return $rate?->hourly_rate ?? $court->hourly_rate;
    }

    public function quote(Court $court, CarbonImmutable $start, CarbonImmutable $end, string $timezone, Collection $rates): int
    {
        $halfHourRates = 0;
        for ($segment = $start; $segment->lt($end); $segment = $segment->addMinutes(30)) {
            $time = $segment->setTimezone($timezone)->format('H:i');
            $halfHourRates += $this->hourlyRate($court, $time, $rates);
        }

        return intdiv($halfHourRates + 1, 2);
    }
}
