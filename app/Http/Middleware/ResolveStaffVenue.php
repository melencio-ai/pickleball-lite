<?php

namespace App\Http\Middleware;

use App\Models\Venue;
use Closure;
use Illuminate\Http\Request;

class ResolveStaffVenue
{
    public function handle(Request $request, Closure $next, string $mode = 'slug')
    {
        $user = $request->user();
        abort_unless($user, 403);
        $query = $user->venues();
        $venue = $mode === 'legacy'
            ? $query->where('venues.id',$user->venue_id)->first()
            : $query->where('venues.slug',$request->route('venueSlug'))->first();
        abort_unless($venue && in_array($venue->pivot->role,['owner','manager','staff'],true), 403);
        $request->route()->forgetParameter('venueSlug');
        $request->attributes->set('venue_context',$venue);
        $request->attributes->set('venue_membership_role',$venue->pivot->role);

        return $next($request);
    }
}
