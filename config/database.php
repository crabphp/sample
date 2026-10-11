<?php

declare(strict_types=1);

return [
    'default' => 'main',

    'connections' => [
        'main' => [
            // "sqlite:/path/to/file.sqlite", or "mysql:host=127.0.0.1;dbname=shop".
            'dsn' => env('DB_DSN', 'sqlite:' . dirname(__DIR__) . '/storage/database.sqlite'),
            'username' => env('DB_USERNAME'),
            'password' => env('DB_PASSWORD'),
            // MySQL only: queries that do not hold the worker while they wait.
            'async' => (bool) env('DB_ASYNC', false),
            // Connections each worker may hold. Unset, 1 for SQLite and 10 for MySQL.
            'pool' => env('DB_POOL'),
            'migrations' => dirname(__DIR__) . '/database/migrations',
        ],
    ],
];
