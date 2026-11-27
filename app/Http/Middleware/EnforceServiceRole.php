<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

final class EnforceServiceRole
{
    public function handle(Request $request, Closure $next)
    {
        $role = config('pickle.service_role');
        $host = $request->getHost();
        $legacy = config('pickle.legacy_host');
        $hosts = match ($role) {
            'marketing' => [config('pickle.product_host')],
            'booking' => [config('pickle.book_host')],
            // Only the original deployment keeps the historical URLs. The
            // canonical booking host is always served by the booking service.
            'management' => [config('pickle.app_host'), $legacy],
            'combined' => app()->environment('local', 'testing')
                ? [config('pickle.product_host'), config('pickle.app_host'), config('pickle.book_host'), $legacy, 'localhost', '127.0.0.1']
                : [],
            default => [],
        };

        // Railway health probes may arrive using an internal hostname.
        if ($request->is('up') && in_array($request->method(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        abort_unless(in_array($host, $hosts, true), 404);

        return $next($request);
    }
}
