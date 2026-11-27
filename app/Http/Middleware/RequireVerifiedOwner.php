<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireVerifiedOwner
{
    public function handle(Request $request,Closure $next)
    {
        if ($request->user()?->self_registered && !$request->user()->email_verified_at) {
            return redirect()->route('app.verify.notice');
        }
        return $next($request);
    }
}
