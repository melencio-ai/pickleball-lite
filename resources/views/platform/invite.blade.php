@extends('layout')
@section('title','Accept invitation')
@section('page-class','narrow')
@section('content')
<section class="panel form-stack">
    <h1>Join {{ $record->name }}</h1>
    <p>You've been invited as {{ $invite->role }}.</p>
    <p class="muted">{{ $invite->email }} · Valid until {{ \Carbon\CarbonImmutable::parse($invite->expires_at)->format('M j, Y') }}</p>
    <form class="form-stack" method="post" action="{{ route('app.invite.accept',['token'=>$token]) }}">
        @csrf
        @if(!$existing)
            <label>Your name<input name="name" maxlength="100" value="{{ old('name') }}" required autocomplete="name"></label>
            <label>Choose password<input type="password" name="password" required minlength="12" maxlength="72" autocomplete="new-password"></label>
            <label>Confirm password<input type="password" name="password_confirmation" required minlength="12" maxlength="72" autocomplete="new-password"></label>
        @endif
        <button class="button">Accept invitation</button>
    </form>
</section>
@endsection
