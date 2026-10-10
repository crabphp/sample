<?php

declare(strict_types=1);

return [
    // "file", "database", "array", or "redis" with the Redis package.
    'store' => env('CACHE_STORE', 'file'),
    'directory' => env('CACHE_DIRECTORY', dirname(__DIR__) . '/storage/cache'),
    'prefix' => 'shop:',
];
