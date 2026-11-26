@extends('layout')
@section('title', 'Venue settings')

@section('content')
    <header class="section-heading page-heading">
        <div><p class="venue-name">{{ $venue->name }}</p><h1>Venue settings</h1></div>
        <a class="button" href="{{ route('settings.customization') }}">Booking setup</a>
    </header>
    <div class="settings-grid">
        <section class="panel">
            <h2>Venue details</h2>
            <form class="form-stack" method="post" action="{{ route('settings.venue') }}">
                @csrf
                @method('PATCH')
                <label>Venue name<input name="name" value="{{ $venue->name }}" maxlength="100" required></label>
                <label>Address<input name="address" value="{{ $venue->address }}" maxlength="255" required></label>
                <label>Contact number<input type="tel" name="phone" value="{{ $venue->phone }}" maxlength="32" required></label>
                <label>Timezone
                    <select name="timezone">
                        @foreach(timezone_identifiers_list() as $timezone)
                            <option @selected($timezone === $venue->timezone)>{{ $timezone }}</option>
                        @endforeach
                    </select>
                </label>
                <button>Save venue</button>
            </form>
        </section>
        <section class="panel">
            <h2>Weekly opening hours</h2>
            <p class="muted">Use 24-hour times in 30-minute increments. Enter 24:00 for midnight.</p>
            <form class="form-stack" method="post" action="{{ route('settings.hours') }}">
                @csrf
                @method('PATCH')
                @foreach([1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday', 0 => 'Sunday'] as $weekday => $day)
                    <fieldset class="hours-row">
                        <legend>{{ $day }}</legend>
                        <label>Open<input type="time" step="1800" name="hours[{{ $weekday }}][opens_at]" value="{{ $hours->get($weekday)?->opens_at ?? '07:00' }}" required></label>
                        <label>Close<input name="hours[{{ $weekday }}][closes_at]" pattern="([01][0-9]|2[0-3]):(00|30)|24:00" value="{{ $hours->get($weekday)?->closes_at ?? '22:00' }}" required></label>
                        <label class="checkbox"><input type="checkbox" name="hours[{{ $weekday }}][closed]" value="1" @checked($hours->get($weekday)?->closed)> Closed</label>
                    </fieldset>
                @endforeach
                <button>Save hours</button>
            </form>
        </section>
        <section class="panel">
            <h2>Courts</h2>
            @foreach($courts as $court)
                <form class="form-row settings-row" method="post" action="{{ route('settings.court.update', $court->id) }}">
                    @csrf
                    @method('PATCH')
                    <label>Name<input name="name" value="{{ $court->name }}" maxlength="80" required></label>
                    <label>Rate (₱/hour)<input name="rate" type="number" min="0" max="999999.99" step="0.01" value="{{ number_format($court->hourly_rate / 100, 2, '.', '') }}" required></label>
                    <label>Court type
                        <select name="surface_type">
                            <option value="">Unspecified</option>
                            <option value="indoor" @selected($court->surface_type === 'indoor')>Indoor</option>
                            <option value="outdoor" @selected($court->surface_type === 'outdoor')>Outdoor</option>
                        </select>
                    </label>
                    <label>Status<select name="active"><option value="1" @selected($court->active)>Active</option><option value="0" @selected(!$court->active)>Inactive</option></select></label>
                    <button class="secondary">Save</button>
                </form>
            @endforeach
            <details class="settings-row">
                <summary>Add a court</summary>
                <form class="form-row" method="post" action="{{ route('settings.court.add') }}">
                    @csrf
                    <input type="hidden" name="active" value="1">
                    <label>Name<input name="name" maxlength="80" required></label>
                    <label>Rate (₱/hour)<input name="rate" type="number" min="0" max="999999.99" step="0.01" required></label>
                    <label>Court type<select name="surface_type"><option value="">Unspecified</option><option value="indoor">Indoor</option><option value="outdoor">Outdoor</option></select></label>
                    <button>Add court</button>
                </form>
            </details>
        </section>
        <section class="panel">
            <h2>Staff access</h2>
            @forelse($staff as $member)
                <div class="section-heading settings-row">
                    <div><strong>{{ $member->name }}</strong><br><span class="muted">{{ $member->email }}</span></div>
                    <form method="post" action="{{ route('settings.staff.remove', $member->id) }}" onsubmit="return confirm('Remove this staff member’s access?')">
                        @csrf
                        @method('DELETE')
                        <button class="secondary">Remove</button>
                    </form>
                </div>
            @empty
                <p class="muted">No staff accounts yet.</p>
            @endforelse
            <details class="settings-row">
                <summary>Add a staff account</summary>
                <form class="form-stack" method="post" action="{{ route('settings.staff.add') }}">
                    @csrf
                    <label>Name<input name="name" maxlength="100" required></label>
                    <label>Email<input name="email" type="email" autocomplete="off" required></label>
                    <label>Initial password<input name="password" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></label>
                    <button>Add staff</button>
                </form>
            </details>
        </section>
    </div>
@endsection
