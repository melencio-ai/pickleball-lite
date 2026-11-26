@extends('layout')
@section('title', 'Court calendar')

@section('content')
    <header class="section-heading page-heading">
        <div>
            <p class="venue-name">{{ $venue->name }}</p>
            <h1>Court calendar</h1>
        </div>
        <div class="actions">
            <a class="button secondary" href="{{ route('staff.open-play', ['date' => $date]) }}#create-session">Create Open Play</a>
            <a class="button secondary" href="{{ route('staff.calendar', ['date' => $date, 'action' => 'block']) }}#calendar-action">Block time</a>
            <a class="button" href="{{ route('staff.calendar', ['date' => $date, 'action' => 'book']) }}#calendar-action">New booking</a>
        </div>
    </header>

    <div class="panel filters calendar-filters">
        <div class="actions">
            <a class="button secondary" href="{{ route('staff.calendar', ['date' => $day->subDay()->toDateString()]) }}" aria-label="Previous day">←</a>
            <a class="button secondary" href="{{ route('staff.calendar') }}">Today</a>
            <a class="button secondary" href="{{ route('staff.calendar', ['date' => $day->addDay()->toDateString()]) }}" aria-label="Next day">→</a>
        </div>
        <form class="actions" method="get" action="{{ route('staff.calendar') }}">
            <label class="visually-hidden" for="calendar-date">Calendar date</label>
            <input id="calendar-date" name="date" type="date" value="{{ $date }}" required>
            <button class="secondary">Go</button>
        </form>
        <div class="calendar-date">
            <strong>{{ $day->format('l, M j, Y') }}</strong>
            <span>{{ $hours && !$hours->closed ? $hours->opens_at.'–'.$hours->closes_at : 'Closed' }}</span>
        </div>
    </div>

    @php($action = request()->query('action'))
    @if(in_array($action, ['book', 'block'], true))
        <section class="panel checkout" id="calendar-action">
            <div class="section-heading">
                <h2>{{ $action === 'book' ? 'Add a booking' : 'Block court time' }}</h2>
                <a href="{{ route('staff.calendar', ['date' => $date]) }}">Close</a>
            </div>
            <form class="form-stack" method="post" action="{{ route($action === 'book' ? 'staff.book' : 'staff.block') }}">
                @csrf
                <input type="hidden" name="date" value="{{ $date }}">
                <div class="form-row">
                    <label>Court
                        <select name="court_id" required>
                            @foreach($courts->where('active', true) as $court)
                                <option value="{{ $court->id }}" @selected(in_array(old('court_id', request()->query('court')), [$court->id, (string) $court->id], true))>{{ $court->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>Start time
                        <input type="time" name="start" step="1800" value="{{ is_string(old('start', request()->query('start'))) ? old('start', request()->query('start')) : '' }}" required>
                    </label>
                    @if($action === 'book')
                        <label>Duration
                            <select name="duration">
                                @foreach($venue->durations() as $minutes)
                                    <option value="{{ $minutes }}" @selected(old('duration', (string) $venue->defaultDuration()) === (string) $minutes)>{{ \App\Models\Venue::durationLabel($minutes) }}</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <label>End time (24-hour)
                            <input name="end" placeholder="21:30" pattern="([01][0-9]|2[0-3]):(00|30)|24:00" value="{{ is_string(old('end')) ? old('end') : '' }}" required>
                        </label>
                    @endif
                </div>
                <div class="form-row">
                    @if($action === 'book')
                        <label>Customer name<input name="customer_name" value="{{ is_string(old('customer_name')) ? old('customer_name') : '' }}" maxlength="100" required></label>
                        <label>Mobile number<input name="customer_phone" value="{{ is_string(old('customer_phone')) ? old('customer_phone') : '' }}" type="tel" maxlength="32" required></label>
                    @else
                        <label>Reason<input name="reason" value="{{ is_string(old('reason')) ? old('reason') : '' }}" maxlength="120" required></label>
                    @endif
                    <button>{{ $action === 'block' ? 'Save block' : ($venue->qr_payments_enabled ? 'Hold for QR payment' : 'Confirm booking') }}</button>
                </div>
            </form>
        </section>
    @endif

    <div class="court-grid">
        @forelse($courts as $court)
            <section class="panel court-column">
                <div class="section-heading">
                    <h2>{{ $court->name }}</h2>
                    <p>{{ $court->active ? '₱'.number_format($court->hourly_rate / 100, 2).'/hr' : 'Inactive' }}</p>
                </div>
                @php($entries = $court->bookings->concat($court->blocks)->sortBy('starts_at'))
                @forelse($entries as $entry)
                    <article class="calendar-entry {{ $entry instanceof \App\Models\CourtBlock ? ($entry->open_play_session_id ? 'open-play-entry' : 'blocked-entry') : '' }}">
                        <time>{{ $entry->starts_at->setTimezone($venue->timezone)->format('g:i A') }} – {{ $entry->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</time>
                        @if($entry instanceof \App\Models\Booking)
                            <strong>{{ $entry->customer_name }}</strong>
                            <span class="muted">{{ $entry->customer_phone }} · {{ $entry->source === 'online' ? 'Online' : 'Staff' }}</span>
                            <span class="muted ref">{{ strtoupper($entry->reference) }}</span>
                            <div class="section-heading">
                                <strong>₱{{ number_format($entry->total / 100, 2) }}</strong>
                                <div class="actions">
                                    @if($entry->cancelled_at)
                                        <span class="status-chip">Cancelled</span>
                                    @endif
                                    @if(!$entry->cancelled_at || $entry->paid_at)
                                        <span class="status-chip {{ $entry->paid_at ? 'paid' : 'unpaid' }}">{{ $entry->paid_at ? 'Paid' : ($entry->paymentExpired() ? 'Payment hold expired' : ($entry->payment_token ? 'Awaiting QR payment' : 'Unpaid')) }}</span>
                                    @endif
                                </div>
                            </div>
                            @unless($entry->cancelled_at)
                                <div class="actions">
                                    @if(!$entry->paid_at && !$entry->payment_token)
                                        <form method="post" action="{{ route('staff.paid', $entry->id) }}">@csrf @method('PATCH')<button class="small secondary">Mark paid</button></form>
                                    @endif
                                    <form method="post" action="{{ route('staff.cancel', $entry->id) }}" onsubmit="return confirm('Cancel this booking and release the time?')">
                                        @csrf @method('PATCH')<button class="small quiet">Cancel booking</button>
                                    </form>
                                </div>
                            @endunless
                            @if($entry->payment_token && !$entry->paid_at && !$entry->cancelled_at && !$entry->paymentExpired())
                                <details class="settings-row">
                                    <summary>Verify Messenger payment</summary>
                                    <div class="actions">
                                        <a href="{{ route('book.payment', $entry->payment_token) }}" target="_blank" rel="noopener">Open payment link</a>
                                        <a href="{{ $entry->payment_messenger_url }}" target="_blank" rel="noopener noreferrer">Open Messenger</a>
                                    </div>
                                    <form class="form-stack" method="post" action="{{ route('staff.paid', $entry->id) }}">
                                        @csrf @method('PATCH')
                                        <label>Amount received (₱)<input type="number" step=".01" min=".01" name="received_amount" required></label>
                                        <label>Transaction reference<input name="payment_reference" maxlength="64" required></label>
                                        <label class="checkbox"><input type="checkbox" name="verified_in_messenger" value="1" required> I matched the Messenger receipt to the incoming payment.</label>
                                        <button class="small secondary">Confirm payment</button>
                                    </form>
                                </details>
                            @elseif($entry->payment_reference)
                                <span class="muted">Transaction {{ $entry->payment_reference }}</span>
                            @endif
                        @elseif($entry->open_play_session_id)
                            <strong>{{ $entry->reason }}</strong>
                            <span class="status-chip">Open Play</span>
                            <a href="{{ route('staff.open-play.show', $entry->open_play_session_id) }}">Manage session</a>
                        @else
                            <strong>Blocked · {{ $entry->reason }}</strong>
                            <form method="post" action="{{ route('staff.unblock', $entry->id) }}">@csrf @method('DELETE')<button class="small quiet">Remove block</button></form>
                        @endif
                    </article>
                @empty
                    <p class="muted">No bookings or blocks.</p>
                @endforelse

                @if($court->slots)
                    <details class="calendar-slots">
                        <summary>Available times</summary>
                        <div class="slots">
                            @foreach($court->slots as $time => $slot)
                                <a class="slot" href="{{ route('staff.calendar', ['date' => $date, 'action' => 'book', 'court' => $court->id, 'start' => $time]) }}#calendar-action">{{ $slot['label'] }}</a>
                            @endforeach
                        </div>
                    </details>
                @endif
            </section>
        @empty
            <div class="panel empty">There are no active courts. An owner can add one in Settings.</div>
        @endforelse
    </div>
@endsection
