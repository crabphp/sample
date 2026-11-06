<?php

declare(strict_types=1);

return [
    'login_limit' => env('LOGIN_LIMIT', 10),
    'register_limit' => env('REGISTER_LIMIT', 10),
    // Requests one worker works on at once; the rest wait their turn. Null for no limit.
    'max_requests' => env('MAX_REQUESTS'),
    'report_disk' => env('REPORTS_DISK', 'reports'),
    // Where shop:mirror-catalogue keeps the feed it downloads.
    'catalogue' => env('CATALOGUE_PATH', storage_path('catalogue.csv')),
    // Where the "warehouse" is that an order is synced with: this application itself. Empty to skip the call.
    'warehouse_url' => env('WAREHOUSE_URL', 'http://127.0.0.1:8080'),
    // Null uses app.url for this application's pretend model. Set MODEL_URL for a real model.
    'model_url' => env('MODEL_URL'),
    // Seconds after which an order that was placed is taken as paid, and minutes after which one never paid is cancelled.
    'settle_after' => 5,
    'cancel_after' => 2,
];
