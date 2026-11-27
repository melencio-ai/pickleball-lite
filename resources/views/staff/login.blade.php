@extends('layout')
@section('title', 'Staff sign in')
@section('page-class', 'narrow')

@section('content')
    <section class="panel login">
        <h1>Staff sign in</h1>
        <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('login.store') }}">
            @csrf
            <label>Email<input type="email" name="email" value="{{ is_string(old('email')) ? old('email') : '' }}" autocomplete="username" required autofocus></label>
            <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
            <button>Sign in</button>
        </form>
    </section>
@endsection
