<div class="calendar-region" data-calendar-grid data-calendar-url="{{ route('staff.calendar', ['date' => $date, 'fragment' => 'grid']) }}">
    @if($courts->isEmpty())
        <div class="panel empty">There are no active courts. An owner can add one in Settings.</div>
    @else
        <div class="compact-calendar-scroll" data-calendar-scroll tabindex="0" aria-label="Court calendar. Scroll to see all courts and times.">
            <div class="compact-calendar-grid" style="--court-count:{{ $courts->count() }}; --slot-count:{{ count($grid['slots']) }}">
                <div class="calendar-corner">Time</div>
                @foreach($courts->values() as $i => $court)
                    <div class="calendar-column-header" style="grid-column:{{ $i + 2 }};grid-row:1">
                        <strong>{{ $court->name }}</strong>
                        <small>{{ $court->active ? '₱'.number_format($court->hourly_rate / 100, 2).'/hr' : 'Inactive' }}</small>
                    </div>
                @endforeach
                @foreach($grid['slots'] as $slot)
                    <div class="calendar-time-label" style="grid-row:{{ $slot['row'] }};grid-column:1">{{ $slot['label'] }}</div>
                    @foreach($courts->values() as $i => $court)
                        <div class="calendar-cell" style="grid-row:{{ $slot['row'] }};grid-column:{{ $i + 2 }}" aria-hidden="true"></div>
                    @endforeach
                @endforeach
                @foreach($grid['free'] as $slot)
                    <a class="calendar-free-slot" style="grid-row:{{ $slot['row'] }};grid-column:{{ $slot['column'] }}"
                       href="{{ route('staff.calendar.modal', ['kind' => 'new', 'date' => $date, 'court' => $slot['court']->id, 'start' => $slot['time']]) }}"
                       data-calendar-modal data-calendar-key="slot-{{ $slot['court']->id }}-{{ $slot['time'] }}"
                       aria-label="Add booking on {{ $slot['court']->name }} at {{ \Carbon\CarbonImmutable::parse($slot['time'])->format('g:i A') }}">
                        <span aria-hidden="true">+</span>
                    </a>
                @endforeach
                @foreach($grid['events'] as $event)
                    <a class="calendar-event calendar-event--{{ $event['status'] }} {{ $event['rows'] === 1 ? 'calendar-event--short' : '' }}"
                       style="grid-row:{{ $event['row'] }} / span {{ $event['rows'] }};grid-column:{{ $event['column'] }} / span {{ $event['columns'] }}"
                       href="{{ route('staff.calendar.modal', ['kind' => $event['kind'], 'record' => $event['id'], 'date' => $date]) }}"
                       data-calendar-modal data-calendar-key="{{ $event['kind'] }}-{{ $event['id'] }}-{{ $event['column'] }}"
                       @if($event['expires']) data-hold-expires="{{ $event['expires'] }}" @endif
                       aria-label="{{ $event['title'] }}, {{ $event['time'] }}, {{ $event['summary'] }}">
                        <span class="calendar-event-title">{{ $event['title'] }}</span>
                        <span class="calendar-event-time">{{ $event['time'] }}</span>
                        <span class="calendar-event-status">{{ $event['summary'] }}</span>
                    </a>
                @endforeach
                @if(!is_null($grid['currentLine']))
                    <div class="calendar-now-line" style="--now-offset:{{ $grid['currentLine'] }}" aria-hidden="true"></div>
                @endif
                @if(empty($grid['slots']))
                    <p class="calendar-closed">Closed · No active reservations for this date.</p>
                @endif
            </div>
        </div>
        <div class="calendar-legend" aria-label="Booking statuses">
            @foreach(['paid' => 'Paid', 'pending' => 'Pending QR', 'unpaid' => 'Unpaid', 'open-play' => 'Open Play', 'blocked' => 'Blocked'] as $status => $label)
                <span><i class="calendar-legend-dot calendar-event--{{ $status }}"></i>{{ $label }}</span>
            @endforeach
        </div>
    @endif
    @if(count($grid['history']))
        <details class="calendar-history">
            <summary>Cancelled and expired bookings ({{ count($grid['history']) }})</summary>
            @foreach($grid['history'] as $item)
                <a class="calendar-history-row" href="{{ route('staff.calendar.modal', ['kind' => 'booking', 'record' => $item['entry']->id, 'date' => $date]) }}"
                   data-calendar-modal data-calendar-key="history-{{ $item['entry']->id }}">
                    <strong>{{ $item['entry']->customer_name }}</strong>
                    <span>{{ $item['court']->name }} · {{ $item['entry']->starts_at->setTimezone($venue->timezone)->format('g:i A') }}</span>
                    <span>{{ $item['entry']->cancelled_at ? 'Cancelled' : 'Hold expired' }}</span>
                </a>
            @endforeach
        </details>
    @endif
</div>
