<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);
        $middleware->redirectGuestsTo(fn () => \App\Services\VenueContext::url('login'));
        $middleware->redirectUsersTo(fn () => \App\Services\VenueContext::onApp() ? route('app.venues') : route('staff.calendar'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
