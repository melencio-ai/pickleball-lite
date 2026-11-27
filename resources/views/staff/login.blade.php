@extends('layouts.auth')
@section('title', 'Sign in')

@section('content')
    <div class="auth-heading">
        <h1>Welcome back</h1>
        <p>Sign in to manage your venue.</p>
    </div>
    <form class="auth-form" method="post" action="{{ \App\Services\VenueContext::url('login.store') }}" data-auth-form>
        @csrf
        <div class="auth-field">
            <label for="login-email">Email address</label>
            <div class="auth-input-wrap">
                <x-auth-icon name="mail" />
                <input id="login-email" type="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" placeholder="you@example.com" autocomplete="username" autocapitalize="none" spellcheck="false" required @error('email') aria-invalid="true" @enderror>
            </div>
        </div>
        <div class="auth-field">
            <label for="login-password">Password</label>
            <div class="auth-input-wrap">
                <x-auth-icon name="lock" />
                <input id="login-password" type="password" name="password" placeholder="Enter password" autocomplete="current-password" required @error('password') aria-invalid="true" @enderror>
                <button class="auth-reveal" type="button" data-password-toggle="login-password" aria-label="Show password" aria-controls="login-password" aria-pressed="false" hidden><span data-eye><x-auth-icon name="eye" /></span><span data-eye-off hidden><x-auth-icon name="eye-off" /></span></button>
            </div>
        </div>
        @if(\App\Services\VenueContext::onApp())
            <div class="auth-options">
                <label class="auth-checkbox"><input name="remember" type="checkbox" value="1" @checked(old('remember'))> Remember me</label>
                @if(\App\Services\PasswordRecovery::enabled())
                    <a href="{{ route('app.password.request') }}">Forgot password?</a>
                @endif
            </div>
            @unless(\App\Services\PasswordRecovery::enabled())
                <p class="auth-help">Need help signing in? Contact your venue owner or Pickle Lite administrator.</p>
            @endunless
        @endif
        <button class="auth-submit" type="submit" data-auth-submit data-busy-text="Signing in…"><span data-submit-label>Sign in</span><x-auth-icon name="arrow-right" /></button>
    </form>
    <div class="auth-secondary">New to Pickle Lite? <a href="{{ route('product.home') }}#get-started">Get started</a></div>
@endsection
