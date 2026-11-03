<?php

declare(strict_types=1);

use App\Providers\AppProvider;
use Crab\App;
use Crab\Auth\TokenAuthProvider;
use Crab\Redis\RedisProvider;
use Crab\Clockwerk\ClockwerkProvider;
use Crab\OpenApi\OpenApiProvider;

// A small shop's API with token authentication, Redis and a dashboard, for trying things out.
// Redis replaces the default stores before Clockwerk observes them; AppProvider adds listeners and tasks last.
return App::configure(__DIR__)
    ->withProviders([
        TokenAuthProvider::class,
        RedisProvider::class,
        ClockwerkProvider::class,
        OpenApiProvider::class,
        AppProvider::class,
    ])
    ->withRouting(
        routes: 'routes/web.php',
        commands: 'routes/console.php',
        health: '/up',
    )
    ->create();
