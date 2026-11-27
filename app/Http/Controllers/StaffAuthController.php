<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    public function create()
    {
        return view('staff.login', ['venue' => \App\Services\VenueContext::onApp() ? null : Venue::query()->where('legacy_slot',1)->first()]);
    }

    public function store(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        if (! Auth::attempt($credentials)) {
            throw ValidationException::withMessages(['email' => 'The email or password is incorrect.']);
        }

        $request->session()->regenerate();

        return redirect()->intended(\App\Services\VenueContext::onApp() ? route('app.venues') : route('staff.calendar'));
    }

    public function destroy(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return \App\Services\VenueContext::redirect('login');
    }
}
