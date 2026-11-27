<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\AuthDestination;
use App\Services\VenueContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    public function create()
    {
        return response()->view('staff.login', ['venue' => VenueContext::onApp() ? null : Venue::query()->where('legacy_slot',1)->first()])
            ->withHeaders(['Cache-Control'=>'private, no-store','Referrer-Policy'=>'no-referrer','X-Robots-Tag'=>'noindex, nofollow']);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $request->validate(['remember' => ['sometimes', 'boolean']]);
        $guard = Auth::guard('web');
        $guard->setRememberDuration(60 * 24 * 30);
        if (! $guard->attempt($credentials, VenueContext::onApp() && $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        if (!\App\Services\VenueContext::onApp() &&
            ($request->user()->self_registered || !$request->user()->venues()->where('venues.id',$request->user()->venue_id)->exists())) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['email'=>'The email or password is incorrect.']);
        }

        $request->session()->regenerate();

        return redirect()->to(AuthDestination::afterLogin($request, $request->user()));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return \App\Services\VenueContext::redirect('login');
    }
}
