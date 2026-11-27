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
        abort_if($mode === 'legacy' && $user->self_registered,403);
        $query = $user->venues();
        $venue = $mode === 'legacy'
            ? $query->where('venues.id',$user->venue_id)->first()
            : $query->where('venues.slug',$request->route('venueSlug'))->first();
        abort_unless($venue && in_array($venue->pivot->role,['owner','manager','staff'],true), 403);
        $request->route()->forgetParameter('venueSlug');
        $request->attributes->set('venue_context',$venue);
        $request->attributes->set('venue_membership_role',$venue->pivot->role);

        if ($venue->self_service && $venue->pivot->role === 'owner' &&
            !\App\Services\VenueContext::is('settings.onboarding*') &&
            !\App\Services\VenueContext::is('settings.subscription*') &&
            !\App\Services\VenueContext::is('settings.website.preview') &&
            \Illuminate\Support\Facades\DB::table('venue_onboarding')->where('venue_id',$venue->id)->whereNull('completed_at')->exists()) {
            return redirect()->route('app.settings.onboarding',['venueSlug'=>$venue->slug]);
        }
        return $next($request);
    }
}
