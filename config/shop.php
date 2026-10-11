<?php

declare(strict_types=1);

return [
    'login_limit' => (int) env('LOGIN_LIMIT', 10),
    'register_limit' => (int) env('REGISTER_LIMIT', 10),
    'reports' => env('REPORTS_PATH', dirname(__DIR__) . '/storage/reports'),
    // Where the "warehouse" is that an order is synced with: this application itself. Empty to skip the call.
    'warehouse_url' => env('WAREHOUSE_URL', 'http://127.0.0.1:8080'),
    // Seconds after which an order that was placed is taken as paid, and minutes after which one never paid is cancelled.
    'settle_after' => 5,
    'cancel_after' => 2,
];
