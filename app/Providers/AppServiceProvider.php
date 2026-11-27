<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        \Illuminate\Auth\Notifications\ResetPassword::createUrlUsing(fn ($user, $token) => route('app.password.reset', ['token'=>$token,'email'=>$user->getEmailForPasswordReset()]));

        RateLimiter::for('booking-payment-status', fn (Request $request) => [
            Limit::perMinute(120)->by('ip:'.$request->ip()),
            Limit::perMinute(30)->by('booking:'.$request->route('token')),
        ]);

        Gate::define('manage-venue', fn (User $user) => \App\Services\VenueContext::role() === 'owner');
        Gate::define('manage-website', fn (User $user) => in_array(\App\Services\VenueContext::role(), ['owner','manager'], true));
        Gate::define('platform-admin', fn (User $user) => $user->platform_admin === true);
    }
}
