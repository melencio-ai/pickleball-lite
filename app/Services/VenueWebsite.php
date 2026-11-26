<?php

namespace App\Services;

use App\Models\Venue;
use Illuminate\Support\Collection;

class VenueWebsite
{
    public const FACILITIES = [
        'parking' => 'Free parking',
        'lighting' => 'Night lighting',
        'restrooms' => 'Restrooms',
        'showers' => 'Showers',
        'waiting' => 'Waiting area',
        'ventilation' => 'Ventilation',
        'rentals' => 'Paddle rentals',
        'food' => 'Food and drinks',
        'wifi' => 'Free Wi-Fi',
        'accessible' => 'Accessible facilities',
    ];

    public const GALLERY = ['gallery1', 'gallery2', 'gallery3', 'gallery4', 'gallery5', 'gallery6'];
    public const DEMO_GALLERY = [
        'courts' => 'Pickleball courts ready for play',
        'doubles' => 'Friends playing a doubles match',
        'open-play' => 'Players meeting during Open Play',
        'equipment' => 'Pickleball paddles and balls',
        'friends' => 'Players enjoying time together after a game',
    ];

    public const DAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

    public static function timeLabel(string $time): string
    {
        if ($time === '24:00') return '12:00 AM (midnight)';
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return ($hour % 12 ?: 12).':'.str_pad((string)$minute, 2, '0', STR_PAD_LEFT).($hour < 12 ? ' AM' : ' PM');
    }

    public static function dayLabel(array $days): string
    {
        if (count($days) === 7) return 'Every day';
        if ($days === [1, 2, 3, 4, 5]) return 'Monday – Friday';
        if ($days === [6, 0]) return 'Saturday – Sunday';

        return implode(', ', array_map(fn ($day) => self::DAYS[$day], $days));
    }

    public function hours(Collection $hours): array
    {
        $groups = [];
        foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
            $row = $hours->get($day);
            $label = !$row || $row->closed ? 'Closed' : self::timeLabel($row->opens_at).' – '.self::timeLabel($row->closes_at);
            $groups[$label][] = $day;
        }

        return $groups;
    }

    public function rates(Venue $venue, Collection $courts, Collection $hours, Pricing $pricing): array
    {
        $rates = $venue->rates()->get()->sortBy(fn ($rate) => $rate->court_id === null);
        $groups = [];
        $minimum = null;
        foreach ($courts as $court) {
            $windows = [];
            foreach ([1, 2, 3, 4, 5, 6, 0] as $day) {
                $opening = $hours->get($day);
                if (!$opening || $opening->closed) continue;
                $daily = $rates->filter(fn ($rate) => in_array($day, $rate->weekdays, true));
                $start = self::minutes($opening->opens_at);
                $end = self::minutes($opening->closes_at);
                $segments = [];
                for ($minute = $start; $minute < $end; $minute += 30) {
                    $rate = $pricing->hourlyRate($court, self::clock($minute), $daily);
                    $minimum = $minimum === null ? $rate : min($minimum, $rate);
                    $last = array_key_last($segments);
                    if ($last !== null && $segments[$last]['rate'] === $rate) {
                        $segments[$last]['end'] = min($end, $minute + 30);
                    } else {
                        $segments[] = ['start' => $minute, 'end' => min($end, $minute + 30), 'rate' => $rate];
                    }
                }
                foreach ($segments as $segment) {
                    $key = implode(':', $segment);
                    if (!isset($windows[$key])) $windows[$key] = $segment + ['days' => []];
                    $windows[$key]['days'][] = $day;
                }
            }
            // Courts with identical effective weekly price schedules share one table.
            $key = json_encode(array_values($windows));
            if (!isset($groups[$key])) $groups[$key] = ['courts' => [], 'windows' => array_values($windows)];
            $groups[$key]['courts'][] = $court->name;
        }

        return ['groups' => array_values($groups), 'minimum' => $minimum];
    }

    private static function minutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));
        return $hour * 60 + $minute;
    }

    public static function clock(int $minute): string
    {
        return sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
    }
}
