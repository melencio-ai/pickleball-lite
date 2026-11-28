<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // Register public documentation before the legacy route group and
            // outside web middleware entirely. No sessions or cookies.
            require __DIR__.'/../routes/help.php';
            \Illuminate\Support\Facades\Route::get('/robots.txt',fn()=>response("User-agent: *\nDisallow:\n",200,['Content-Type'=>'text/plain; charset=UTF-8']));
            \Illuminate\Support\Facades\Route::middleware('web')->group(__DIR__.'/../routes/web.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(\App\Http\Middleware\EnforceServiceRole::class);
        $middleware->trustProxies(at: '*', headers: Request::HEADER_X_FORWARDED_PROTO);
        $middleware->redirectGuestsTo(fn () => \App\Services\VenueContext::url('login'));
        $middleware->redirectUsersTo(fn (Request $request) => \App\Services\AuthDestination::home($request->user()));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
