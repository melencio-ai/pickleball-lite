<div data-calendar-fragment>
    <div data-calendar-errors class="notice error" role="alert" hidden></div>
    @if($kind === 'new')
        <h2 id="calendar-modal-title">What would you like to add?</h2>
        <p class="muted">{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j, Y') }} @if($selectedCourt) · {{ $selectedCourt->name }} @endif @if($selectedStart) · {{ \Carbon\CarbonImmutable::parse($selectedStart)->format('g:i A') }} @endif</p>
        <div class="survey-choices calendar-create-choices">
            @foreach(['book' => ['calendar-days','Court booking','Reserve a court for a customer.'], 'open-play' => ['users','Open Play','Create a session for multiple players.'], 'block' => ['lock','Block time','Make a court unavailable.']] as $type => $choice)
                <a class="survey-choice" href="{{ \App\Services\VenueContext::url('staff.calendar.modal', ['kind' => $type, 'date' => $date, 'court' => $selectedCourt?->id, 'start' => $selectedStart ?: null]) }}" data-calendar-modal>
                    <x-booking-icon :name="$choice[0]" /><span><strong>{{ $choice[1] }}</strong><small>{{ $choice[2] }}</small></span><x-booking-icon name="chevron-right" />
                </a>
            @endforeach
        </div>
    @elseif(in_array($kind, ['book','block','open-play'], true))
        <a href="{{ \App\Services\VenueContext::url('staff.calendar.modal', ['kind' => 'new', 'date' => $date, 'court' => $selectedCourt?->id, 'start' => $selectedStart ?: null]) }}" data-calendar-modal class="calendar-modal-back"><x-booking-icon name="chevron-left" /> Back</a>
        <h2 id="calendar-modal-title">{{ $kind === 'book' ? 'Add a booking' : ($kind === 'block' ? 'Block court time' : 'Create Open Play') }}</h2>
        @if($courts->isEmpty())
            <p class="notice">There are no active courts. Add one in Settings.</p>
        @else
            <form class="form-stack calendar-modal-form" method="post" action="{{ \App\Services\VenueContext::url(match($kind) {'book'=>'staff.book','block'=>'staff.block',default=>'staff.open-play.store'}) }}" data-calendar-form>
                @csrf
                <label>Date<input type="date" name="date" value="{{ old('date', $date) }}" required></label>
                @if($kind === 'open-play')
                    <label>Session name<input name="name" maxlength="100" value="{{ old('name','Open Play') }}" required></label>
                    <fieldset class="calendar-court-picker">
                        <legend>Assign courts</legend>
                        @foreach($courts as $court)
                            <label class="checkbox"><input type="checkbox" name="court_ids[]" value="{{ $court->id }}" @checked(in_array((string)$court->id, array_map('strval', (array) old('court_ids', $selectedCourt ? [$selectedCourt->id] : [])), true))> {{ $court->name }}</label>
                        @endforeach
                    </fieldset>
                @else
                    <label>Court<select name="court_id" required>
                        @foreach($courts as $court)
                            <option value="{{ $court->id }}" @selected((string)old('court_id', $selectedCourt?->id) === (string)$court->id)>{{ $court->name }}</option>
                        @endforeach
                    </select></label>
                @endif
                <div class="calendar-form-grid">
                    <label>Start time<input type="time" step="1800" name="start" value="{{ old('start',$selectedStart) }}" required></label>
                    @if($kind === 'book')
                        <label>Duration<select name="duration">
                            @foreach($venue->durations() as $minutes)
                                <option value="{{ $minutes }}" @selected((string)old('duration', $venue->defaultDuration()) === (string)$minutes)>{{ \App\Models\Venue::durationLabel($minutes) }}</option>
                            @endforeach
                        </select></label>
                    @else
                        <label>End time (24-hour)<input name="end" placeholder="21:00" pattern="([01][0-9]|2[0-3]):(00|30)|24:00" value="{{ old('end','') }}" required></label>
                    @endif
                </div>
                @if($kind === 'book')
                    <label>Full name<input name="customer_name" value="{{ old('customer_name','') }}" maxlength="100" required></label>
                    <label>Mobile number<input name="customer_phone" type="tel" value="{{ old('customer_phone','') }}" maxlength="32" required></label>
                @elseif($kind === 'block')
                    <label>Reason<input name="reason" value="{{ old('reason','') }}" maxlength="120" required></label>
                @else
                    <div class="calendar-form-grid">
                        <label>Maximum players<input type="number" name="capacity" min="1" max="500" value="{{ old('capacity',24) }}" required></label>
                        <label>Price per player (PHP)<input name="price" type="number" min=".01" max="999999.99" step=".01" value="{{ old('price',200) }}" required></label>
                        <label>Skill level<select name="skill_level">@foreach(['all'=>'All skill levels','beginner'=>'Beginner','intermediate'=>'Intermediate','advanced'=>'Advanced'] as $value => $label)<option value="{{ $value }}" @selected(old('skill_level','all') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label>Repeat<select name="repeat" data-calendar-repeat>@foreach(['none'=>'One-time session','daily'=>'Every day','weekly'=>'Every week'] as $value => $label)<option value="{{ $value }}" @selected(old('repeat','none') === $value)>{{ $label }}</option>@endforeach</select></label>
                        <label data-calendar-occurrences>Number of sessions<input type="number" name="occurrences" min="1" max="30" value="{{ old('occurrences',1) }}"></label>
                    </div>
                @endif
                <button class="button">{{ match($kind) {'block'=>'Save block','open-play'=>'Publish Open Play',default=>$venue->qr_payments_enabled ? 'Hold for QR payment' : 'Confirm booking'} }}</button>
            </form>
        @endif
    @elseif($kind === 'booking')
        <h2 id="calendar-modal-title">Booking details</h2>
        <div class="calendar-record-title">
            <strong>{{ $entry->customer_name }}</strong>
            <span class="muted">{{ strtoupper($entry->reference) }}</span>
        </div>
        <dl class="calendar-detail-list">
            <div><dt>Mobile</dt><dd><a href="tel:{{ preg_replace('/[^+0-9]/','',$entry->customer_phone) }}">{{ $entry->customer_phone }}</a></dd></div>
            <div><dt>Court</dt><dd>{{ $entry->court->name }}</dd></div>
            <div><dt>Date</dt><dd>{{ $entry->starts_at->setTimezone($venue->timezone)->format('D, M j, Y') }}</dd></div>
            <div><dt>Time</dt><dd>{{ $entry->starts_at->setTimezone($venue->timezone)->format('g:i A') }} – {{ $entry->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</dd></div>
            <div><dt>Duration</dt><dd>{{ $entry->starts_at->diffInMinutes($entry->ends_at) < 60 ? (int)$entry->starts_at->diffInMinutes($entry->ends_at).' minutes' : \App\Models\Venue::durationLabel((int)$entry->starts_at->diffInMinutes($entry->ends_at)) }}</dd></div>
            <div><dt>Source</dt><dd>{{ ucfirst($entry->source) }}</dd></div>
        </dl>
        <div class="calendar-payment-summary">
            <div><small>{{ $entry->paid_at ? 'Amount paid' : 'Booking total' }}</small><strong>₱{{ number_format($entry->total/100,2) }}</strong></div>
            <div>
                <span class="status-chip {{ $entry->paid_at && !$entry->cancelled_at ? 'paid' : 'unpaid' }}">{{ $entry->cancelled_at ? 'Cancelled' : ($entry->paid_at ? 'Paid' : ($entry->paymentExpired() ? 'Payment hold expired' : ($entry->payment_token ? 'Pending payment' : 'Unpaid'))) }}</span>
                @if(!$entry->cancelled_at && !$entry->paid_at && $entry->payment_token && !$entry->paymentExpired())
                    <small>Hold: <span data-calendar-countdown="{{ $entry->payment_expires_at->getTimestamp() }}">{{ max(0, (int)now()->diffInSeconds($entry->payment_expires_at,false)) }}s</span></small>
                @endif
            </div>
        </div>
        @if($entry->payment_reference)<p class="muted">Transaction: {{ $entry->payment_reference }}</p>@endif
        @if($entry->payment_token && !$entry->cancelled_at && !$entry->paid_at)
            <div class="actions calendar-modal-actions">
                <a class="button secondary" href="{{ \App\Services\VenueContext::url('book.payment',$entry->payment_token) }}" target="_blank" rel="noopener noreferrer"><x-booking-icon name="link" /> Payment link</a>
                <a class="button secondary" href="{{ $entry->payment_messenger_url }}" target="_blank" rel="noopener noreferrer"><x-booking-icon name="message-circle" /> Open Messenger</a>
            </div>
            @if(!$entry->paymentExpired())
                <details class="calendar-verify">
                    <summary>Verify Messenger payment</summary>
                    <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('staff.paid',$entry->id) }}" data-calendar-form>
                        @csrf @method('PATCH')
                        <label>Received amount (PHP)<input type="number" min=".01" step=".01" name="received_amount" required></label>
                        <label>Transaction reference<input name="payment_reference" maxlength="64" required></label>
                        <label class="checkbox"><input type="checkbox" name="verified_in_messenger" value="1" required> I matched the Messenger receipt to the incoming payment.</label>
                        <button class="button">Confirm payment</button>
                    </form>
                </details>
            @else
                <p class="notice">Reconcile late payments with the player in Messenger.</p>
            @endif
        @elseif(!$entry->paid_at && !$entry->cancelled_at)
            <form method="post" action="{{ \App\Services\VenueContext::url('staff.paid',$entry->id) }}" data-calendar-form>@csrf @method('PATCH')<button class="button">Mark paid</button></form>
        @endif
        @unless($entry->cancelled_at)
            <details class="calendar-danger"><summary>Cancel booking</summary>
                <p class="muted">Release the court. Reconcile any paid amount separately.</p>
                <form method="post" action="{{ \App\Services\VenueContext::url('staff.cancel',$entry->id) }}" data-calendar-form>@csrf @method('PATCH')<button class="button secondary">Confirm cancellation</button></form>
            </details>
        @endunless
    @elseif($kind === 'blocked')
        <h2 id="calendar-modal-title">Blocked time</h2>
        <div class="calendar-record-title"><strong>{{ $entry->reason }}</strong></div>
        <dl class="calendar-detail-list">
            <div><dt>Court</dt><dd>{{ $court->name }}</dd></div>
            <div><dt>Date</dt><dd>{{ $entry->starts_at->setTimezone($venue->timezone)->format('D, M j, Y') }}</dd></div>
            <div><dt>Time</dt><dd>{{ $entry->starts_at->setTimezone($venue->timezone)->format('g:i A') }} – {{ $entry->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</dd></div>
        </dl>
        <details class="calendar-danger"><summary>Remove block</summary><p class="muted">Make this court available again.</p>
            <form method="post" action="{{ \App\Services\VenueContext::url('staff.unblock',$entry->id) }}" data-calendar-form>@csrf @method('DELETE')<button class="button secondary">Confirm removal</button></form>
        </details>
    @elseif($kind === 'session')
        <h2 id="calendar-modal-title">Open Play details</h2>
        <div class="calendar-record-title"><strong>{{ $session->name }}</strong><span class="muted">{{ $session->courts->pluck('name')->join(', ') }} · {{ $session->starts_at->setTimezone($venue->timezone)->format('D, M j · g:i A') }} – {{ $session->ends_at->setTimezone($venue->timezone)->format('g:i A') }}</span></div>
        <div class="calendar-session-stats">
            <div><small>Capacity</small><strong>{{ $session->capacity }}</strong></div>
            <div><small>Paid players</small><strong>{{ $paid->count() }}</strong></div>
            <div><small>Pending</small><strong>{{ $active->count()-$paid->count() }}</strong></div>
            <div><small>Available</small><strong>{{ $session->cancelled_at ? 0 : max(0,$session->capacity-$active->count()) }}</strong></div>
        </div>
        <progress class="open-play-capacity" value="{{ $active->count() }}" max="{{ $session->capacity }}" aria-label="Spots taken"></progress>
        <dl class="calendar-detail-list">
            <div><dt>Price per player</dt><dd>₱{{ number_format($session->price/100,2) }}</dd></div>
            <div><dt>Collected (active)</dt><dd>₱{{ number_format($paid->sum('total')/100,2) }}</dd></div>
            <div><dt>Checked in</dt><dd>{{ $paid->whereNotNull('checked_in_at')->count() }}</dd></div>
        </dl>
        <h3>Player roster</h3>
        <div class="calendar-player-list">
            @forelse($players->take(8) as $player)
                <div class="calendar-player"><strong>{{ $player->customer_name }}</strong><span class="status-chip {{ $player->paymentStatus()==='confirmed' ? 'paid' : 'unpaid' }}">{{ $player->checked_in_at && !$player->cancelled_at ? 'Checked in' : ucfirst($player->paymentStatus()) }}</span>
                    @if($player->paid_at && !$player->cancelled_at && !$session->cancelled_at && !$player->checked_in_at && $session->ends_at->gt(now()))
                        <form method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.action',[$session->id,$player->id,'check-in']) }}" data-calendar-form>@csrf @method('PATCH')<button class="small secondary">Check in</button></form>
                    @endif
                </div>
            @empty<p class="muted">No players registered yet.</p>
            @endforelse
            @if($players->count()>8)<p class="muted">Showing 8 of {{ $players->count() }} registrations.</p>@endif
        </div>
        <a class="button" href="{{ \App\Services\VenueContext::url('staff.open-play.show',$session->id) }}">Manage session</a>
        @if(!$session->cancelled_at && $session->ends_at->gt(now()) && $active->count()<$session->capacity)
            <details class="calendar-verify"><summary>Add walk-in player</summary>
                <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.register',$session->id) }}" data-calendar-form>
                    @csrf
                    <label>Full name<input name="customer_name" maxlength="100" required></label>
                    <label>Mobile number<input name="customer_phone" type="tel" maxlength="32" required></label>
                    <button class="button">Hold spot for QR payment</button>
                </form>
            </details>
        @endif
        @if(!$session->cancelled_at && $session->starts_at->gt(now()))
            <details class="calendar-danger"><summary>Cancel session</summary><p class="muted">Release all courts and cancel registrations. Reconcile paid amounts in Messenger.</p>
                <form method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.cancel',$session->id) }}" data-calendar-form>@csrf @method('PATCH')<button class="button secondary">Confirm cancellation</button></form>
            </details>
        @endif
    @endif
</div>
