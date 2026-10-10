<?php

declare(strict_types=1);

return [
    // "sqlite:/path/to/file.sqlite", or "mysql:host=127.0.0.1;dbname=shop".
    'dsn' => env('DB_DSN', 'sqlite:' . dirname(__DIR__) . '/storage/database.sqlite'),
    'username' => env('DB_USERNAME'),
    'password' => env('DB_PASSWORD'),
    'migrations' => dirname(__DIR__) . '/database/migrations',
];
