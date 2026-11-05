<?php

declare(strict_types=1);

return [
    'carrier' => env('BROADCAST_CARRIER', 'local'),
    'prefix' => env('BROADCAST_PREFIX', 'crab:'),
];
