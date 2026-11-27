<?php

namespace App\Services;

use App\Models\Venue;
use Illuminate\Http\RedirectResponse;

final class VenueContext
{
    public static function venue(): Venue
    {
        $venue = request()->attributes->get('venue_context');
        abort_unless($venue instanceof Venue, 404);

        return $venue;
    }

    public static function role(): ?string
    {
        return request()->attributes->get('venue_membership_role');
    }

    public static function onApp(): bool
    {
        return request()->getHost() === config('pickle.app_host');
    }

    public static function onBooking(): bool
    {
        return request()->getHost() === config('pickle.book_host');
    }

    public static function url(string $name, mixed $parameters = [], bool $absolute = true, ?Venue $venue = null): string
    {
        $venue ??= request()->attributes->get('venue_context');
        $public = $name === 'landing' || $name === 'book' || str_starts_with($name, 'book.')
            || $name === 'open-play' || str_starts_with($name, 'open-play.');
        if ($name === 'landing.demo-image') {
            return route(self::onBooking() || self::onApp() ? 'public.demo-image' : $name, $parameters, $absolute);
        }
        if ($public && $venue && (self::onApp() || self::onBooking() || !$venue->legacy_slot)) {
            return route('public.'.$name, ['venueSlug'=>$venue->slug] + (array) $parameters, $absolute);
        }
        if (in_array($name, ['login','login.store','logout'], true) && (self::onApp() || self::onBooking())) {
            return route('app.'.$name, $parameters, $absolute);
        }
        if ((str_starts_with($name, 'staff.') || $name === 'settings' || str_starts_with($name, 'settings.')) && self::onApp()) {
            abort_unless($venue instanceof Venue, 404);
            return route('app.'.$name, ['venueSlug'=>$venue->slug] + (array) $parameters, $absolute);
        }
        return route($name, $parameters, $absolute);
    }

    public static function redirect(string $name, mixed $parameters = []): RedirectResponse
    {
        return redirect()->to(self::url($name, $parameters));
    }

    public static function is(string $pattern): bool
    {
        return request()->routeIs($pattern, 'public.'.$pattern, 'app.'.$pattern);
    }
}
