@extends('layouts.auth')
@section('title','Verify your email')
@section('content')
<div class="auth-heading"><h1>Check your email</h1><p>Open the verification link sent to {{ auth()->user()->email }} to activate your 30-day free trial.</p></div>
<p class="auth-help">The link expires after 60 minutes. Check spam if you cannot find it. Sign in with this same account if you open the link on another device.</p>
<form class="auth-form" method="post" action="{{ route('app.verify.resend') }}" data-auth-form>@csrf<button class="auth-submit" data-auth-submit type="submit" data-busy-text="Sending…"><span data-submit-label>Resend verification email</span><x-auth-icon name="mail"/></button></form>
<div class="auth-secondary"><button class="legal-text-button" type="button" aria-haspopup="dialog" aria-controls="sign-out-confirmation" data-sign-out-open>Sign out</button></div>
@endsection
