@props(['name'])
<svg class="auth-icon" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>@break
        @case('lock')<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4M12 15v2"/>@break
        @case('eye')<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>@break
        @case('eye-off')<path d="m3 3 18 18M10.6 5.1 12 5c6.5 0 10 7 10 7a19 19 0 0 1-3.1 3.9M6.2 6.2C3.5 8.3 2 12 2 12s3.5 7 10 7c1.9 0 3.5-.5 4.8-1.3M9.9 9.9a3 3 0 0 0 4.2 4.2"/>@break
        @case('arrow-right')<path d="M4 12h15m-6-6 6 6-6 6"/>@break
        @case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18M8 14h2m4 0h2m-8 3h2"/>@break
        @case('users')<circle cx="9" cy="7" r="3"/><path d="M2 20v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3M16 4a3 3 0 0 1 0 6m2 3a5 5 0 0 1 4 4v3"/>@break
        @case('qr')<rect x="3" y="3" width="6" height="6" rx="1"/><rect x="15" y="3" width="6" height="6" rx="1"/><rect x="3" y="15" width="6" height="6" rx="1"/><path d="M13 13h4v4h4v4h-4m-4-4v4m4-8h4v2"/>@break
        @case('globe')<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>@break
        @case('user')<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/>@break
    @endswitch
</svg>
