<?php

declare(strict_types=1);

return [
    // "database", "memory", or "redis" with the Redis package.
    'store' => env('QUEUE_STORE', 'database'),
    'sleep' => 0.5,
    // Mail has workers of its own, so a flood of confirmations never holds up the rest.
    'supervisors' => [
        'mail' => ['queues' => ['mail'], 'workers' => 1, 'tries' => 4, 'backoff' => [2, 5, 15]],
        'default' => ['queues' => ['high', 'default'], 'workers' => 2, 'concurrency' => 4],
    ],
];
