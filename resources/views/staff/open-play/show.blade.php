@extends('layout')
@section('title', 'Manage Open Play')
@section('content')
    <div class="page-heading section-heading">
        <div>
            <a href="{{ \App\Services\VenueContext::url('staff.open-play') }}">Back to Open Play</a>
            <h1>{{ $record->name }}</h1>
            <p class="muted">{{ $record->starts_at->setTimezone($venue->timezone)->format('D, M j · g:i A') }} – {{ $record->ends_at->setTimezone($venue->timezone)->format('g:i A') }} · {{ $record->courts->pluck('name')->join(', ') }}</p>
        </div>
        <span class="status-chip {{ $record->cancelled_at ? 'unpaid' : 'paid' }}">{{ $record->cancelled_at ? 'Cancelled' : ($record->ends_at->lte(now()) ? 'Ended' : 'Published') }}</span>
    </div>
    <section class="panel">
        <div class="open-play-stats">
            <div><small>Capacity</small><strong>{{ $record->capacity }}</strong></div>
            <div><small>Confirmed</small><strong>{{ $paid->count() }}</strong></div>
            <div><small>Pending payment</small><strong>{{ $active->count() - $paid->count() }}</strong></div>
            <div><small>Available</small><strong>{{ $record->cancelled_at ? 0 : max(0, $record->capacity - $active->count()) }}</strong></div>
            <div><small>Collected (active)</small><strong>₱{{ number_format($collected / 100, 2) }}</strong></div>
        </div>
        <progress class="open-play-capacity" value="{{ $active->count() }}" max="{{ $record->capacity }}" aria-label="Spots taken"></progress>
        <p class="muted">₱{{ number_format($record->price / 100, 2) }} per player · {{ $record->levelLabel() }} · {{ $paid->whereNotNull('checked_in_at')->count() }} checked in</p>
        @if(!$record->cancelled_at && $record->starts_at->gt(now()))
            <div class="actions">
                <a class="button secondary" href="{{ \App\Services\VenueContext::url('open-play', ['date' => $record->starts_at->setTimezone($venue->timezone)->toDateString(), 'session' => $record->id]) }}" target="_blank" rel="noopener">Public session link</a>
                <details>
                    <summary>Cancel session</summary>
                    <p class="muted">Release the courts and cancel all registrations. Reconcile any payments in Messenger.</p>
                    <form method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.cancel', $record->id) }}">@csrf @method('PATCH')<button class="button secondary">Confirm cancellation</button></form>
                </details>
            </div>
        @endif
    </section>
    @if(!$record->cancelled_at && $record->ends_at->gt(now()) && $active->count() < $record->capacity)
        <section class="panel open-play-create">
            <h2>Add walk-in player</h2>
            <form method="post" class="form-row" action="{{ \App\Services\VenueContext::url('staff.open-play.register', $record->id) }}">
                @csrf
                <label>Full name<input name="customer_name" maxlength="100" required></label>
                <label>Mobile number<input name="customer_phone" type="tel" maxlength="32" required></label>
                <button class="button">Hold spot for QR payment</button>
            </form>
        </section>
    @endif
    <section class="panel open-play-admin-list">
        <h2>Player roster</h2>
        <div class="open-play-table-wrap">
            <table class="open-play-table">
                <thead><tr><th>Player</th><th>Payment</th><th>Arrival</th><th>Actions</th></tr></thead>
                <tbody>
                    @forelse($registrations as $player)
                        <tr>
                            <td><strong>{{ $player->customer_name }}</strong><br><small>{{ $player->customer_phone }}</small><br><small class="muted">{{ $player->reference }} · {{ $player->source }}</small></td>
                            <td>
                                <span class="status-chip {{ $player->paymentStatus() === 'confirmed' ? 'paid' : 'unpaid' }}">{{ ucfirst($player->paymentStatus()) }}</span>
                                <small class="muted">₱{{ number_format($player->total / 100, 2) }}</small>
                                @if($player->paid_at && $player->cancelled_at)<small class="muted">Previously paid; reconcile manually.</small>@endif
                                @if($player->payment_reference)<small class="muted">{{ $player->payment_reference }}</small>@endif
                            </td>
                            <td>{{ $player->checked_in_at ? 'Checked in' : '—' }}</td>
                            <td>
                                @if($player->paymentStatus() === 'pending' && !$record->cancelled_at)
                                    <details><summary>Verify Messenger payment</summary>
                                        <form class="form-stack" method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.action', [$record->id, $player->id, 'paid']) }}">
                                            @csrf @method('PATCH')
                                            <a href="{{ \App\Services\VenueContext::url('open-play.payment', $player->payment_token) }}" target="_blank" rel="noopener">Open payment link</a>
                                            <x-messenger-link :url="$player->payment_messenger_url" >Open Messenger</x-messenger-link>
                                            <label>Received amount (PHP)<input name="received_amount" required value="{{ number_format($player->total / 100, 2, '.', '') }}"></label>
                                            <label>Transaction reference<input name="payment_reference" maxlength="64" required></label>
                                            <label class="checkbox"><input type="checkbox" name="verified_in_messenger" value="1" required> I matched the Messenger receipt to the incoming payment.</label>
                                            <button class="button small">Confirm payment</button>
                                        </form>
                                    </details>
                                @endif
                                @if($player->paid_at && !$player->cancelled_at && !$record->cancelled_at && !$player->checked_in_at && $record->ends_at->gt(now()))
                                    <form method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.action', [$record->id, $player->id, 'check-in']) }}">@csrf @method('PATCH')<button class="button small secondary">Check in</button></form>
                                @endif
                                @if(!$player->cancelled_at && !$record->cancelled_at)
                                    <details><summary>Cancel registration</summary>
                                        <p class="muted">Releases this spot; refunds are handled in Messenger.</p>
                                        <form method="post" action="{{ \App\Services\VenueContext::url('staff.open-play.action', [$record->id, $player->id, 'cancel']) }}">@csrf @method('PATCH')<button class="button secondary small">Confirm cancellation</button></form>
                                    </details>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="muted">No players registered yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
