<?php

namespace App\Http\Middleware;

use App\Services\LegalPolicies;
use App\Services\VenueContext;
use Closure;
use Illuminate\Http\Request;

final class RequireLegalTerms
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || !LegalPolicies::needsAcceptance($request->user())) {
            return $next($request);
        }
        if ($request->isMethod('GET')) {
            $request->session()->put('legal.intended',$request->fullUrl());
        }
        $route = VenueContext::onApp() ? 'app.legal.accept' : 'legal.accept';
        return redirect()->route($route);
    }
}
