<?php

declare(strict_types=1);

return [
    // "database", "memory", or "redis" with the Redis package.
    'store' => env('QUEUE_STORE', 'database'),
    'sleep' => 0.5,
    // Mail has workers of its own, so a flood of confirmations never holds up the rest. The warehouse's
    // jobs have a timeout, and run one at a time: a worker ended for one that is stuck ends no other.
    'supervisors' => [
        'mail' => ['queues' => ['mail'], 'workers' => 1, 'tries' => 4, 'backoff' => [2, 5, 15]],
        'warehouse' => ['queues' => ['warehouse'], 'workers' => 1, 'concurrency' => 1],
        'default' => ['queues' => ['high', 'default'], 'workers' => 2, 'concurrency' => 4],
    ],
];
