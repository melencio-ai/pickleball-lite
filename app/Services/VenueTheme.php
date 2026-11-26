<?php

namespace App\Services;

final class VenueTheme
{
    public const DEFAULT = '#155542';

    public static function tokens(?string $color): array
    {
        $primary = preg_match('/^#[a-fA-F0-9]{6}$/D', $color ?? '') ? strtolower($color) : self::DEFAULT;
        $onPrimary = self::luminance($primary) > 0.1791287847 ? '#000000' : '#ffffff';
        $tint = self::mix($primary, '#ffffff', 0.94);
        $soft = self::mix($primary, '#ffffff', 0.88);
        $border = self::mix($primary, '#ffffff', 0.72);
        // Text on white or tinted surfaces must remain readable for very light brand colors.
        $ink = $primary;
        for ($i = 0; $i < 100 && self::contrast($ink, $soft) < 4.5; $i++) {
            $ink = self::mix($primary, '#000000', ($i + 1) / 100);
        }
        $focus = $ink;
        $hover = self::mix($primary, $onPrimary === '#ffffff' ? '#000000' : '#ffffff', 0.12);

        return [
            'brand-primary' => $primary,
            'brand-on-primary' => $onPrimary,
            'brand-hover' => $hover,
            'brand-ink' => $ink,
            'brand-tint' => $tint,
            'brand-soft' => $soft,
            'brand-border' => $border,
            'brand-focus' => $focus,
        ];
    }

    public static function style(?string $color): string
    {
        $style = [];
        foreach (self::tokens($color) as $name => $value) {
            $style[] = '--'.$name.':'.$value;
        }

        return implode(';', $style);
    }

    public static function contrast(string $a, string $b): float
    {
        $first = self::luminance($a);
        $second = self::luminance($b);

        return (max($first, $second) + 0.05) / (min($first, $second) + 0.05);
    }

    private static function rgb(string $color): array
    {
        return array_map('hexdec', str_split(substr($color, 1), 2));
    }

    private static function luminance(string $color): float
    {
        $values = array_map(function ($channel) {
            $value = $channel / 255;
            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, self::rgb($color));

        return $values[0] * 0.2126 + $values[1] * 0.7152 + $values[2] * 0.0722;
    }

    private static function mix(string $a, string $b, float $amount): string
    {
        $channels = array_map(fn ($x, $y) => (int) round($x * (1 - $amount) + $y * $amount), self::rgb($a), self::rgb($b));

        return sprintf('#%02x%02x%02x', ...$channels);
    }
}
