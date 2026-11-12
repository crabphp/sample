<?php

declare(strict_types=1);

$serverAddress = (string) env('SERVER_ADDRESS', 'tcp://127.0.0.1:8080');
$defaultUrl = str_replace(
    ['tcp://0.0.0.0:', 'tcp://[::]:', 'tcp://'],
    ['http://127.0.0.1:', 'http://[::1]:', 'http://'],
    $serverAddress,
);

return [
    'name' => env('APP_NAME', 'Crab Shop'),
    // Use the listening address locally; APP_URL names the public address behind a proxy.
    'url' => env('APP_URL') ?? $defaultUrl,
    // The secret cookie sessions are signed with; php cua key:generate writes APP_KEY into .env.
    'key' => env('APP_KEY'),

    // Show the details of a failure to the client. Never in production.
    'debug' => env('APP_DEBUG', false),

    // The time zone every date is made, stored and read in. Kept in UTC; the shop's schedule says its own.
    'timezone' => env('APP_TIMEZONE', 'UTC'),
];
