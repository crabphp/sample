<?php

declare(strict_types=1);

use App\Http\AdminsOnly;
use Crab\Auth\TokenAuth;
use Crab\Http\RateLimit;

return [
    'version' => env('API_VERSION', '1.0.0'),
    'description' => 'The shop catalogue, orders, assistant, and administration API.',
    'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
    'output' => env('OPENAPI_PATH', 'openapi.json'),
    'strict' => true,
    'servers' => [env('APP_URL', 'http://127.0.0.1:8080')],
    // The health handler and the Clockwerk dashboard are operational views,
    // not part of the shop's public API contract.
    'exclude' => [
        'GET /up',
        'GET /clockwerk',
        'GET /clockwerk/api',
        'POST /clockwerk/api/jobs/retry',
        'POST /clockwerk/api/jobs/forget',
    ],
    'security' => [
        'bearer' => ['type' => 'http', 'scheme' => 'bearer'],
        'cookie' => ['type' => 'apiKey', 'in' => 'cookie', 'name' => 'auth'],
    ],
    'middleware' => [
        TokenAuth::class => ['security' => 'bearer', 'responses' => [401]],
        RateLimit::class => ['responses' => [429]],
        AdminsOnly::class => ['responses' => [403]],
    ],
];
