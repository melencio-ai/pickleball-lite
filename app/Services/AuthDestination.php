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
        if ($user->self_registered && !$user->email_verified_at) return route('app.verify.notice');
        if ($user->platform_admin) {
            return route('app.admin.index');
        }

        $venues = $user->venues()->wherePivotIn('role', ['owner', 'manager', 'staff'])
            ->orderBy('venues.id')->limit(2)->get(['venues.id', 'venues.slug','venues.self_service']);
        if ($venues->count() === 1) {
            $venue=$venues->first();
            if ($venue->self_service && $venue->pivot->role === 'owner' &&
                \Illuminate\Support\Facades\DB::table('venue_onboarding')->where('venue_id',$venue->id)->whereNull('completed_at')->exists()) {
                return route('app.settings.onboarding',['venueSlug'=>$venue->slug]);
            }
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
                    ? preg_match('#^/(?:venues(?:/|$)|admin(?:/|$)|notifications(?:/|$)|verify-email/[0-9]+/[a-f0-9]{40}$|invite/[A-Za-z0-9]{64}$)#D', $path)
                    : preg_match('#^/staff/(?:calendar|open-play|settings|website|booking-setup)(?:/|$)#D', $path);
                if ($allowed) {
                    return $intended;
                }
            }
        }

        return self::home($user);
    }
}
