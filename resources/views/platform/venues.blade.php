@extends('layout')
@section('title','My venues')
@section('content')
<header class="section-heading page-heading"><div><h1>My venues</h1><p class="muted">Choose a venue to manage.</p></div>@can('platform-admin')<a class="button" href="{{ route('app.admin.index') }}">Platform admin</a>@endcan</header>
<div class="tenant-venue-list">
    @forelse($venues as $record)
        <article class="panel tenant-venue-card">
            <span class="tenant-color" style="background:{{ \App\Services\VenueTheme::tokens($record->brand_color)['brand-primary'] }}"></span>
            <div><h2>{{ $record->name }}</h2><p class="muted">{{ ucfirst($record->pivot->role) }} · {{ ucfirst($record->status) }} · {{ $record->published ? 'Published' : 'Unpublished' }}</p></div>
            <div class="actions"><a class="button" href="{{ route($record->self_service && $record->pivot->role==='owner' && \Illuminate\Support\Facades\DB::table('venue_onboarding')->where('venue_id',$record->id)->whereNull('completed_at')->exists()?'app.settings.onboarding':'app.staff.calendar',['venueSlug'=>$record->slug]) }}">Manage venue <x-booking-icon name="chevron-right" /></a>
                @if($record->acceptsPublicBookings())<a class="button secondary" href="{{ route('public.landing',['venueSlug'=>$record->slug]) }}" target="_blank" rel="noopener">View website</a>@endif
            </div>
        </article>
    @empty
        <section class="panel"><h2>No venue access yet</h2><p>Ask your venue owner or Pickle Lite administrator for an invitation.</p></section>
    @endforelse
</div>
@endsection
