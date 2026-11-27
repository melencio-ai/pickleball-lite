@extends('layouts.auth')
@section('title','Review updated terms')
@section('content')
<div class="auth-heading"><h1>Review our Terms of Use</h1><p>Please read the current terms before continuing to your venue workspace.</p></div>
<form class="auth-form" method="post" action="{{ route(\App\Services\VenueContext::onApp() ? 'app.legal.accept.store' : 'legal.accept.store') }}" data-auth-form>
    @csrf
    <x-legal-acceptance :legal="$legal"/>
    <button class="auth-submit" type="submit" data-auth-submit data-busy-text="Saving…"><span data-submit-label>Accept and continue</span><x-auth-icon name="arrow-right"/></button>
</form>
<div class="auth-secondary"><button type="button" class="legal-text-button" aria-haspopup="dialog" aria-controls="sign-out-confirmation" data-sign-out-open>Sign out</button></div>
@endsection
