@props(['name'])
<svg class="booking-icon" width="18" height="18" aria-hidden="true" focusable="false">
    <use href="{{ asset('booking-icons.svg') }}?v=5#{{ $name }}"></use>
</svg>
