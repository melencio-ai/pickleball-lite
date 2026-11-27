@extends('layouts.auth')
@section('title','Reset your password')
@section('content')
<div class="auth-heading"><h1>Forgot your password?</h1><p>Enter your account email to request a secure reset link.</p></div>
<form class="auth-form" method="post" action="{{ route('app.password.email') }}" data-auth-form>
    @csrf
    <div class="auth-field"><label for="recovery-email">Email address</label><div class="auth-input-wrap"><x-auth-icon name="mail" /><input id="recovery-email" name="email" type="email" autocomplete="username" required value="{{ is_string(old('email')) ? old('email') : '' }}"></div></div>
    <button class="auth-submit" type="submit" data-auth-submit data-busy-text="Sending…"><span data-submit-label>Send reset link</span><x-auth-icon name="arrow-right" /></button>
</form>
<div class="auth-secondary"><a href="{{ route('app.login') }}">Back to sign in</a></div>
@endsection
