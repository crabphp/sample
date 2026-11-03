<?php

declare(strict_types=1);

use App\Plugins\AppPlugin;
use Crab\Actors\ActorsPlugin;
use Crab\App;
use Crab\Auth\TokenAuthPlugin;
use Crab\Broadcast\BroadcastPlugin;
use Crab\Clockwerk\ClockwerkPlugin;
use Crab\OpenApi\OpenApiPlugin;
use Crab\Queue\QueuePlugin;
use Crab\Redis\RedisPlugin;
use Crab\Runs\RunsPlugin;
use Crab\Schedule\SchedulePlugin;

// A small shop's API with token authentication, Redis and a dashboard, for trying things out.
// It uses every optional plugin. Redis comes after the queue and broadcast, whose store and carrier it replaces,
// and before Clockwerk, which observes them; AppPlugin adds listeners and tasks last.
return App::configure(__DIR__)
    ->withPlugins([
        QueuePlugin::class,
        SchedulePlugin::class,
        RunsPlugin::class,
        BroadcastPlugin::class,
        ActorsPlugin::class,
        TokenAuthPlugin::class,
        RedisPlugin::class,
        ClockwerkPlugin::class,
        OpenApiPlugin::class,
        AppPlugin::class,
    ])
    ->withRouting(
        routes: 'routes/web.php',
        commands: 'routes/console.php',
        health: '/up',
    )
    ->create();
