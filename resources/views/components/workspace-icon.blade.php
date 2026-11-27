@props(['name'])
<svg class="workspace-icon" viewBox="0 0 24 24" width="19" height="19" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
@switch($name)
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
@case('user')<circle cx="12" cy="8" r="3.5"/><path d="M4 21v-2a6 6 0 0 1 6-6h4a6 6 0 0 1 6 6v2"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>@break
@case('shield')<path d="m12 3 8 3v6c0 5-4 8-8 10-4-2-8-5-8-10V6zM9 12l2 2 4-5"/>@break
@case('file')<path d="M5 3h10l4 4v14H5zM14 3v5h5M8 12h8m-8 4h8"/>@break
@case('building')<rect x="5" y="3" width="14" height="18" rx="1"/><path d="M9 7h.1M15 7h.1M9 11h.1M15 11h.1M10 21v-5h4v5"/>@break
@case('globe')<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18M8 14h2m4 0h2"/>@break
@case('users')<circle cx="9" cy="7" r="3"/><path d="M2 20v-3a5 5 0 0 1 5-5h4a5 5 0 0 1 5 5v3M16 4a3 3 0 0 1 0 6m2 3a5 5 0 0 1 4 4v3"/>@break
@case('card')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 9h18M7 15h4"/>@break
@case('settings')<circle cx="12" cy="12" r="4"/><path d="m12 2 2 3 3 1 3-1 2 4-2 2v2l2 2-2 4-3-1-3 1-2 3-2-3-3-1-3 1-2-4 2-2v-2L2 9l2-4 3 1 3-1z"/>@break
@case('logout')<path d="M10 3H4v18h6M9 12h12m-4-4 4 4-4 4"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@endswitch
</svg>
