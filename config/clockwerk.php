<?php

declare(strict_types=1);

return [
    // Open the dashboard with /clockwerk?token=<this>. Closed to everyone while it is not set.
    'token' => env('CLOCKWERK_TOKEN'),
    'name' => env('APP_NAME', 'Crab Shop'),
];
