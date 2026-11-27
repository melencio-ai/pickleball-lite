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

        // Named email limits use separate counters per authenticated administrator.
        // Browser requests stay on the settings screen instead of a generic 429.
        foreach (['save'=>5,'test'=>5,'activation'=>10] as $action=>$attempts) {
            RateLimiter::for('platform-email-'.$action, function (Request $request) use ($action,$attempts) {
                return Limit::perMinutes(10,$attempts)
                    ->by('user:'.$request->user()->getAuthIdentifier())
                    ->response(function (Request $request, array $headers) use ($action) {
                        $seconds = max(1,(int)($headers['Retry-After'] ?? 600));
                        $message = 'Too many email service requests. Try again in '.$seconds.' seconds.';
                        if ($request->expectsJson()) {
                            return response()->json(['message'=>$message,'retry_after'=>$seconds],429,$headers);
                        }
                        return redirect()->route('app.admin.email.index',[],303,$headers)
                            ->with('email_rate_limit',['action'=>$action,'seconds'=>$seconds]);
                    });
            });
        }

        Gate::define('manage-venue', fn (User $user) => \App\Services\VenueContext::role() === 'owner');
        Gate::define('manage-website', fn (User $user) => in_array(\App\Services\VenueContext::role(), ['owner','manager'], true));
        Gate::define('platform-admin', fn (User $user) => $user->platform_admin === true);
    }
}
