<?php

return [
    'service_role' => env('SERVICE_ROLE', env('APP_ENV') === 'production' ? 'management' : 'combined'),
    'product_host' => env('PICKLE_PRODUCT_HOST', 'picklelite.cloud'),
    'app_host' => env('PICKLE_APP_HOST', 'app.picklelite.cloud'),
    'book_host' => env('PICKLE_BOOK_HOST', 'book.picklelite.cloud'),
    'password_reset_enabled' => (bool) env('PICKLE_PASSWORD_RESET_ENABLED', false),
    'demo_slug' => env('PICKLE_DEMO_SLUG'),
    'legacy_host' => env('PICKLE_LEGACY_HOST', 'pickleball-lite-production.up.railway.app'),
];
