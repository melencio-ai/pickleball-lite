@extends('layout')
@section('title', 'Calendar details')
@section('page-class', 'narrow')
@section('content')
    <p><a href="{{ \App\Services\VenueContext::url('staff.calendar', ['date' => $date]) }}">Back to calendar</a></p>
    <section class="panel">
        @include('staff.calendar.modal')
    </section>
@endsection
