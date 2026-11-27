<?php

namespace App\Http\Middleware;

use App\Models\Venue;
use Closure;
use Illuminate\Http\Request;

class ResolvePublicVenue
{
    public function handle(Request $request, Closure $next, string $mode = 'slug')
    {
        $venue = $mode === 'legacy'
            ? Venue::query()->where('legacy_slot',1)->first()
            : Venue::query()->where('slug',$request->route('venueSlug'))->first();
        abort_unless($venue, 404);
        $request->route()->forgetParameter('venueSlug');
        $request->attributes->set('venue_context',$venue);
        // Existing private payment/confirmation URLs remain accessible for reconciliation.
        $existing = $request->routeIs('*.payment','*.payment.status','*.confirmed','*.image');
        abort_unless($existing || $venue->acceptsPublicBookings(), 404);

        return $next($request);
    }
}
