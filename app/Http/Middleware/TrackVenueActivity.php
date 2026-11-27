<?php

namespace App\Http\Middleware;

use App\Services\PlatformRewards;
use App\Services\VenueContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

final class TrackVenueActivity
{
    public function handle(Request $request,Closure $next)
    {
        $name=$request->route()?->getName();
        $role=VenueContext::role();
        $page=in_array($name,['app.staff.calendar','app.staff.open-play'],true) ||
            (in_array($role,['owner','manager'],true) && $name==='app.settings.website') ||
            ($role==='owner' && in_array($name,['app.settings','app.settings.customization'],true));
        $document=$request->isMethod('GET') && !$request->ajax() && !$request->expectsJson() &&
            !$request->has('fragment') && !$request->header('X-Calendar-Fragment') &&
            !str_contains(strtolower($request->header('Sec-Purpose','').' '.$request->header('Purpose','')),'prefetch') &&
            in_array($request->header('Sec-Fetch-Dest','document'),['document'],true);
        $mutation=!in_array($request->method(),['GET','HEAD','OPTIONS'],true) &&
            (in_array($name,['app.staff.book','app.staff.paid','app.staff.cancel','app.staff.block','app.staff.unblock','app.staff.open-play.store','app.staff.open-play.register','app.staff.open-play.action','app.staff.open-play.cancel'],true) ||
             in_array($name,['app.settings.venue','app.settings.hours','app.settings.court.add','app.settings.court.update','app.settings.website.save','app.settings.branding','app.settings.payments','app.settings.rules','app.settings.rates.add','app.settings.rates.update'],true));
        if ($page && $document) $this->record($request);
        $response=$next($request);
        if ($mutation && ($response->isSuccessful() || $response->isRedirection()) && !$request->session()->has('errors')) $this->record($request);
        return $response;
    }

    private function record(Request $request): void
    {
        try { PlatformRewards::record(VenueContext::venue(),$request->user()); }
        catch(\Throwable $error) {
            // A loyalty telemetry failure must not block venue operations.
            Log::warning('Venue rewards activity could not be recorded.',['exception'=>get_class($error)]);
        }
    }
}
