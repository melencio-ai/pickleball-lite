@extends('layouts.auth')
@section('title','Accept invitation')
@section('content')
<div class="auth-heading">
    <h1>Join {{ $record->name }}</h1>
    <p>You've been invited as {{ $invite->role }}.</p>
</div>
<div class="auth-invite-detail"><strong>{{ $invite->email }}</strong><span>Valid until {{ \Carbon\CarbonImmutable::parse($invite->expires_at)->format('M j, Y') }}</span></div>
<form class="auth-form" method="post" action="{{ route('app.invite.accept',['token'=>$token]) }}" data-auth-form>
    @csrf
    @if(!$existing)
        <div class="auth-field"><label for="invite-name">Your name</label><div class="auth-input-wrap"><x-auth-icon name="user" /><input id="invite-name" name="name" maxlength="100" value="{{ is_string(old('name')) ? old('name') : '' }}" required autocomplete="name"></div></div>
        <div class="auth-field"><label for="invite-password">Choose password</label><div class="auth-input-wrap"><x-auth-icon name="lock" /><input id="invite-password" type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"><button type="button" class="auth-reveal" data-password-toggle="invite-password" aria-label="Show password" aria-controls="invite-password" aria-pressed="false" hidden><span data-eye><x-auth-icon name="eye" /></span><span data-eye-off hidden><x-auth-icon name="eye-off" /></span></button></div><p class="auth-help">Use at least 12 characters.</p></div>
        <div class="auth-field"><label for="invite-confirm">Confirm password</label><div class="auth-input-wrap"><x-auth-icon name="lock" /><input id="invite-confirm" type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password"><button type="button" class="auth-reveal" data-password-toggle="invite-confirm" aria-label="Show confirmation password" aria-controls="invite-confirm" aria-pressed="false" hidden><span data-eye><x-auth-icon name="eye" /></span><span data-eye-off hidden><x-auth-icon name="eye-off" /></span></button></div></div>
    @endif
    <x-legal-acceptance :legal="$legal" />
    <button class="auth-submit" type="submit" data-auth-submit data-busy-text="Accepting invitation…"><span data-submit-label>Accept invitation</span><x-auth-icon name="arrow-right" /></button>
</form>
@if(!$existing)<div class="auth-secondary">This is a private, one-time invitation.</div>@else<div class="auth-secondary"><a href="{{ route('app.venues') }}">Back to my venues</a></div>@endif
@endsection
