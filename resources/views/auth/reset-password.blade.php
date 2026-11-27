@extends('layouts.auth')
@section('title','Choose a new password')
@section('content')
<div class="auth-heading"><h1>Set a new password</h1><p>Choose a password of at least 12 characters.</p></div>
<form class="auth-form" method="post" action="{{ route('app.password.update') }}" data-auth-form>
    @csrf
    <input type="hidden" name="token" value="{{ $token }}">
    <div class="auth-field"><label for="reset-email">Email address</label><div class="auth-input-wrap"><x-auth-icon name="mail" /><input id="reset-email" name="email" type="email" autocomplete="username" required value="{{ is_string(old('email')) ? old('email') : $email }}"></div></div>
    <div class="auth-field"><label for="new-password">New password</label><div class="auth-input-wrap"><x-auth-icon name="lock" /><input id="new-password" name="password" type="password" autocomplete="new-password" required minlength="12" maxlength="72"><button type="button" class="auth-reveal" data-password-toggle="new-password" aria-label="Show password" aria-pressed="false" aria-controls="new-password" hidden><span data-eye><x-auth-icon name="eye" /></span><span data-eye-off hidden><x-auth-icon name="eye-off" /></span></button></div></div>
    <div class="auth-field"><label for="confirm-password">Confirm password</label><div class="auth-input-wrap"><x-auth-icon name="lock" /><input id="confirm-password" name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="72"><button type="button" class="auth-reveal" data-password-toggle="confirm-password" aria-label="Show confirmation password" aria-pressed="false" aria-controls="confirm-password" hidden><span data-eye><x-auth-icon name="eye" /></span><span data-eye-off hidden><x-auth-icon name="eye-off" /></span></button></div></div>
    <button class="auth-submit" type="submit" data-auth-submit data-busy-text="Updating…"><span data-submit-label>Update password</span><x-auth-icon name="arrow-right" /></button>
</form>
<div class="auth-secondary"><a href="{{ route('app.password.request') }}">Request another reset link</a></div>
@endsection
