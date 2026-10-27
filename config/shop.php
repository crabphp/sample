<?php

declare(strict_types=1);

return [
    'login_limit' => (int) env('LOGIN_LIMIT', 10),
    'register_limit' => (int) env('REGISTER_LIMIT', 10),
    // Requests one worker works on at once; the rest wait their turn. Null for no limit.
    'max_requests' => env('MAX_REQUESTS') === null ? null : (int) env('MAX_REQUESTS'),
    'reports' => env('REPORTS_PATH', dirname(__DIR__) . '/storage/reports'),
    // Where shop:mirror-catalogue keeps the feed it downloads.
    'catalogue' => env('CATALOGUE_PATH', dirname(__DIR__) . '/storage/catalogue.csv'),
    // Where the "warehouse" is that an order is synced with: this application itself. Empty to skip the call.
    'warehouse_url' => env('WAREHOUSE_URL', 'http://127.0.0.1:8080'),
    // Seconds after which an order that was placed is taken as paid, and minutes after which one never paid is cancelled.
    'settle_after' => 5,
    'cancel_after' => 2,
];
