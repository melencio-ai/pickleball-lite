<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LegacyHost
{
    public function handle(Request $request, Closure $next)
    {
        $local = app()->environment('local','testing') && in_array($request->getHost(), ['localhost','127.0.0.1'], true);
        abort_unless($local || $request->getHost() === config('pickle.legacy_host'), 404);

        return $next($request);
    }
}
