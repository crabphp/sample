<?php

declare(strict_types=1);

return [
    // "file", "database", "array", or "redis" with the Redis package.
    'store' => env('CACHE_STORE', 'file'),
    'directory' => env('CACHE_DIRECTORY', storage_path('cache')),
    'prefix' => 'shop:',
];
