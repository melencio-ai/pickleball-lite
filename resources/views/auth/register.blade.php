@extends('layouts.auth')
@section('title','Start your free trial')
@section('auth-page-class','auth-register')
@section('content')
<div class="auth-heading"><h1>Start your free trial</h1><p>30 days free. No credit card required. For venue owners.</p></div>
<form class="auth-form" method="post" action="{{ route('app.register.store') }}" data-auth-form>
    @csrf
    <div class="auth-register-fields">
        <div class="auth-field"><label for="signup-name">Full name</label><div class="auth-input-wrap"><x-auth-icon name="user"/><input id="signup-name" name="name" required maxlength="100" autocomplete="name" value="{{ old('name') }}"></div></div>
        <div class="auth-field"><label for="signup-email">Email address</label><div class="auth-input-wrap"><x-auth-icon name="mail"/><input id="signup-email" type="email" name="email" required maxlength="255" autocomplete="email" value="{{ old('email') }}"></div></div>
        <div class="auth-field"><label for="signup-password">Password</label><div class="auth-input-wrap"><x-auth-icon name="lock"/><input id="signup-password" type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><button type="button" class="auth-reveal" data-password-toggle="signup-password" aria-label="Show password" aria-controls="signup-password" aria-pressed="false" hidden><span data-eye><x-auth-icon name="eye"/></span><span data-eye-off hidden><x-auth-icon name="eye-off"/></span></button></div><p class="auth-help">At least 12 characters.</p></div>
        <div class="auth-field"><label for="signup-confirm">Confirm password</label><div class="auth-input-wrap"><x-auth-icon name="lock"/><input id="signup-confirm" type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password"></div></div>
    </div>
    <div hidden aria-hidden="true"><label>Leave blank<input name="website" tabindex="-1" autocomplete="off"></label></div>
    <x-legal-acceptance :legal="$legal"/>
    <p class="auth-help">Your trial begins when you verify your email. No automatic charges. Free setup. Continue after your trial for ₱1,000/month or ₱10,000/year per venue.</p>
    <button type="submit" class="auth-submit" data-auth-submit data-busy-text="Creating account…"><span data-submit-label>Create free account</span><x-auth-icon name="arrow-right"/></button>
</form>
<div class="auth-secondary">Already have an account? <a href="{{ route('app.login') }}">Sign in</a></div>
@endsection
