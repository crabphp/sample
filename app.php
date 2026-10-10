<?php

declare(strict_types=1);

use App\Providers\AppProvider;
use Crab\App;
use Crab\Auth\AuthProvider;
use Crab\Auth\TokenAuthProvider;
use Crab\Cache\CacheProvider;
use Crab\Database\DatabaseProvider;
use Crab\Events\EventsProvider;
use Crab\HttpClient\HttpClientProvider;
use Crab\Queue\QueueProvider;
use Crab\Redis\RedisProvider;
use Crab\Schedule\ScheduleProvider;
use Crab\Clockwerk\ClockwerkProvider;

// A small shop's API, with every part of the framework switched on, for trying things out.
// Events come before the queue and the schedule, which report through them; Redis after the
// cache and the queue, whose stores it can take the place of; the dashboard after them all.
return App::configure(__DIR__)
    ->withProviders([
        DatabaseProvider::class,
        AuthProvider::class,
        TokenAuthProvider::class,
        CacheProvider::class,
        EventsProvider::class,
        QueueProvider::class,
        ScheduleProvider::class,
        HttpClientProvider::class,
        RedisProvider::class,
        ClockwerkProvider::class,
        AppProvider::class,
    ])
    ->withRouting(
        routes: 'routes/web.php',
        commands: 'routes/console.php',
        health: '/up',
    )
    ->create();
