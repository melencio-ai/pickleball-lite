<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

final class AuthDestination
{
    public static function home(?User $user): string
    {
        if (!VenueContext::onApp()) {
            return route($user ? 'staff.calendar' : 'login');
        }
        if (!$user) {
            return route('app.login');
        }
        if ($user->platform_admin) {
            return route('app.admin.index');
        }

        $venues = $user->venues()->wherePivotIn('role', ['owner', 'manager', 'staff'])
            ->orderBy('venues.id')->limit(2)->get(['venues.id', 'venues.slug']);
        if ($venues->count() === 1) {
            return route('app.staff.calendar', ['venueSlug' => $venues->first()->slug]);
        }

        return route('app.venues');
    }

    public static function afterLogin(Request $request, User $user): string
    {
        $intended = $request->session()->pull('url.intended');
        if (is_string($intended) && !str_contains($intended, '\\') && !preg_match('/[\x00-\x20\x7f]/', $intended)) {
            $parts = parse_url($intended);
            if (is_array($parts) && !isset($parts['user']) && !isset($parts['pass']) &&
                ($parts['scheme'] ?? '') === $request->getScheme() &&
                ($parts['host'] ?? '') === $request->getHost() &&
                ($parts['port'] ?? ($request->isSecure() ? 443 : 80)) === $request->getPort()) {
                $path = $parts['path'] ?? '/';
                $allowed = VenueContext::onApp()
                    ? preg_match('#^/(?:venues(?:/|$)|admin(?:/|$)|invite/[A-Za-z0-9]{64}$)#D', $path)
                    : preg_match('#^/staff/(?:calendar|open-play|settings|website|booking-setup)(?:/|$)#D', $path);
                if ($allowed) {
                    return $intended;
                }
            }
        }

        return self::home($user);
    }
}
