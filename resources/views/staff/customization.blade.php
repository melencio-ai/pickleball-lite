@extends('layout')
@section('title', 'Booking setup')

@section('content')
    <header class="section-heading page-heading">
        <div><p class="venue-name">{{ $venue->name }}</p><h1>Booking setup</h1></div>
        <div class="actions">
            <a class="button secondary" href="{{ \App\Services\VenueContext::url('settings') }}">Venue settings</a>
            <a class="button secondary" href="{{ \App\Services\VenueContext::url('settings.website') }}">Website settings</a>
            <a class="button" href="{{ \App\Services\VenueContext::url('book') }}" target="_blank" rel="noopener">Preview booking page</a>
        </div>
    </header>

    <nav class="setup-nav" aria-label="Booking setup sections">
        <a href="#branding">Branding</a>
        <a href="#booking-rules">Booking rules</a>
        <a href="#rates">Rates</a>
        <a href="#closures">Closures</a>
        <a href="#payments">Payments</a>
        <a href="#sharing">Booking QR</a>
    </nav>

    <div class="settings-grid">
        <section class="panel" id="branding">
            <h2>Venue website and booking branding</h2>
            <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('settings.branding') }}" enctype="multipart/form-data">
                @csrf
                @method('PATCH')
                <label>Brand color<input type="color" name="brand_color" value="{{ $venue->brand_color ?? '#155542' }}"></label>
                <label>Map link<input type="url" name="map_url" value="{{ $venue->map_url }}" placeholder="https://maps.google.com/…"></label>
                <label>Messenger link<input type="url" name="messenger_url" value="{{ $venue->messenger_url }}" placeholder="https://m.me/…"></label>
                @foreach(['logo' => 'Logo', 'cover' => 'Cover photo'] as $kind => $label)
                    <label>{{ $label }} ({{ $kind === 'logo' ? '1 MB max' : '2 MB max' }})
                        <input type="file" name="{{ $kind }}" accept="image/jpeg,image/png,image/webp">
                    </label>
                    @if($media->has($kind))
                        <div class="actions">
                            <img class="media-preview" src="{{ \App\Services\VenueContext::url('book.image', ['kind' => $kind, 'version' => $media[$kind]->version]) }}" alt="{{ $label }}">
                            <label class="checkbox"><input type="checkbox" name="remove_{{ $kind }}" value="1"> Remove {{ strtolower($label) }}</label>
                        </div>
                    @endif
                @endforeach
                <button>Save branding</button>
            </form>
        </section>

        <section class="panel" id="booking-rules">
            <h2>Booking rules</h2>
            <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('settings.rules') }}">
                @csrf
                @method('PATCH')
                <fieldset class="hours-row">
                    <legend>Allowed durations</legend>
                    @foreach(\App\Models\Venue::BOOKING_DURATIONS as $minutes)
                        <label class="checkbox">
                            <input type="checkbox" name="booking_durations[]" value="{{ $minutes }}" @checked(in_array($minutes, $venue->durations(), true))>
                            {{ \App\Models\Venue::durationLabel($minutes) }}
                        </label>
                    @endforeach
                </fieldset>
                <label>Advance booking (days)
                    <input type="number" min="0" max="180" name="advance_days" value="{{ $venue->advance_days }}" required>
                    <span class="muted">Set to 0 for same-day public bookings only.</span>
                </label>
                <label>Minimum notice (minutes)
                    <input type="number" min="0" max="10080" name="minimum_notice" value="{{ $venue->minimum_notice }}" required>
                </label>
                <label>Cancellation policy
                    <textarea name="cancellation_policy" rows="4" maxlength="1500">{{ $venue->cancellation_policy }}</textarea>
                </label>
                <button>Save rules</button>
            </form>
        </section>

        <section class="panel" id="rates">
            <h2>Time-based rates</h2>
            <p class="muted">Court-specific rates take priority. Other times use the all-court rate or the court’s default rate.</p>
            @php($days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'])
            @foreach($rates as $rate)
                <div class="section-heading settings-row">
                    <div>
                        <strong>{{ $rate->court?->name ?? 'All courts' }}</strong>
                        <div class="muted">{{ implode(', ', array_intersect_key($days, array_flip($rate->weekdays))) }} · {{ $rate->starts_at }}–{{ $rate->ends_at }} · ₱{{ number_format($rate->hourly_rate / 100, 2) }}/hr</div>
                    </div>
                    <div class="actions">
                        <a href="{{ \App\Services\VenueContext::url('settings.customization', ['rate' => $rate->id]) }}#rate-form">Edit</a>
                        <form method="post" action="{{ \App\Services\VenueContext::url('settings.rates.remove', $rate->id) }}" onsubmit="return confirm('Remove this rate?')">
                            @csrf @method('DELETE')
                            <button class="small secondary">Remove</button>
                        </form>
                    </div>
                </div>
            @endforeach
            <form class="form-stack settings-row" id="rate-form" method="post" action="{{ $editing ? \App\Services\VenueContext::url('settings.rates.update', $editing->id) : \App\Services\VenueContext::url('settings.rates.add') }}">
                @csrf
                @if($editing) @method('PATCH') @endif
                <div class="section-heading">
                    <h3>{{ $editing ? 'Edit rate' : 'Add a rate' }}</h3>
                    @if($editing)<a href="{{ \App\Services\VenueContext::url('settings.customization') }}#rates">Cancel edit</a>@endif
                </div>
                <label>Applies to
                    <select name="court_id">
                        <option value="">All courts</option>
                        @foreach($courts as $court)
                            <option value="{{ $court->id }}" @selected($editing?->court_id === $court->id)>{{ $court->name }}</option>
                        @endforeach
                    </select>
                </label>
                <fieldset class="hours-row">
                    <legend>Days</legend>
                    @foreach($days as $weekday => $day)
                        <label class="checkbox">
                            <input type="checkbox" name="weekdays[]" value="{{ $weekday }}" @checked(in_array($weekday, $editing?->weekdays ?? [], true))>
                            {{ $day }}
                        </label>
                    @endforeach
                </fieldset>
                <div class="form-row">
                    <label>Start<input type="time" step="1800" name="starts_at" value="{{ $editing?->starts_at }}" required></label>
                    <label>End (24-hour)<input name="ends_at" pattern="([01][0-9]|2[0-3]):(00|30)|24:00" value="{{ $editing?->ends_at }}" placeholder="22:00" required></label>
                    <label>Rate (₱/hour)<input type="number" name="rate" min="0" max="999999.99" step="0.01" value="{{ $editing ? number_format($editing->hourly_rate / 100, 2, '.', '') : '' }}" required></label>
                </div>
                <button>{{ $editing ? 'Save rate' : 'Add rate' }}</button>
            </form>
        </section>

        <div class="form-stack">
            <section class="panel" id="closures">
                <h2>Holiday and one-off closures</h2>
                @forelse($closures as $closure)
                    <div class="section-heading settings-row">
                        <div><strong>{{ $closure->date }}</strong><br><span class="muted">{{ $closure->reason }}</span></div>
                        <form method="post" action="{{ \App\Services\VenueContext::url('settings.closures.remove', $closure->id) }}">
                            @csrf @method('DELETE')
                            <button class="small secondary">Reopen date</button>
                        </form>
                    </div>
                @empty
                    <p class="muted">No upcoming closures.</p>
                @endforelse
                <form class="form-row settings-row" method="post" action="{{ \App\Services\VenueContext::url('settings.closures.add') }}">
                    @csrf
                    <label>Date<input type="date" name="date" min="{{ now($venue->timezone)->toDateString() }}" required></label>
                    <label>Reason<input name="reason" maxlength="120" required></label>
                    <button>Close date</button>
                </form>
            </section>

            <section class="panel" id="payments">
                <h2>QR payments</h2>
                <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('settings.payments') }}">
                    @csrf
                    @method('PATCH')
                    <label>Base merchant EMV code
                        <textarea name="payment_qr_base" rows="3" maxlength="512" autocomplete="off">{{ $venue->payment_qr_base }}</textarea>
                        <span class="muted">Pickle Lite replaces any amount in the base code with the booking total.</span>
                    </label>
                    <label>Payment verification Messenger link
                        <input type="url" name="payment_messenger_url" value="{{ $venue->payment_messenger_url }}" placeholder="https://m.me/yourvenue">
                        <span class="muted">Accepts m.me and Facebook /messages/t/ links.</span>
                    </label>
                    <label class="checkbox"><input type="checkbox" name="qr_payments_enabled" value="1" @checked($venue->qr_payments_enabled)> Require QR payment for new bookings</label>
                    <label class="checkbox"><input type="checkbox" name="provider_confirmed" value="1"> My payment provider supports generating amount-prefilled QR codes from this base code.</label>
                    <button>Save payment settings</button>
                </form>
            </section>

            <section class="panel" id="sharing">
                <h2>Share your venue</h2>
                <label>Website link<input type="url" value="{{ \App\Services\VenueContext::url('landing') }}" readonly></label>
                <label>Booking link<input type="url" value="{{ \App\Services\VenueContext::url('book') }}" readonly></label>
                <div class="share-qr" data-qr-url="{{ \App\Services\VenueContext::url('book') }}" aria-label="Booking page QR code"></div>
                <a class="button secondary" data-qr-download download="booking-qr.svg" hidden>Download QR code</a>
                <script src="{{ asset('vendor/qrcode-generator-1.4.4.js') }}" defer></script>
                <script src="{{ asset('booking-qr.js') }}" defer></script>
            </section>
        </div>
    </div>
@endsection
