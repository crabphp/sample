<?php

declare(strict_types=1);

return [
    'url' => env('REDIS_URL', 'redis://127.0.0.1:6379'),
    'prefix' => 'shop:',
];
