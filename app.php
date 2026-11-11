<?php

declare(strict_types=1);

use App\Setup;
use Crab\Actors\ActorsPlugin;
use Crab\App;
use Crab\Arc\ArcPlugin;
use Crab\Auth\TokenAuthPlugin;
use Crab\Broadcast\BroadcastPlugin;
use Crab\Clockwerk\ClockwerkPlugin;
use Crab\OpenApi\OpenApiPlugin;
use Crab\Queue\QueuePlugin;
use Crab\Redis\RedisPlugin;
use Crab\Runs\RunsPlugin;
use Crab\S3\S3Plugin;
use Crab\Schedule\SchedulePlugin;
use Crab\Tide\TidePlugin;

// A small shop's API with token authentication, Redis and a dashboard, for trying things out.
// It uses every optional plugin. Redis comes after the queue and broadcast, whose store and carrier it replaces,
// and before Clockwerk, which observes them; its Setup adds listeners and tasks after them all.
return App::configure(__DIR__)
    ->withPlugins([
        ArcPlugin::class,
        QueuePlugin::class,
        SchedulePlugin::class,
        RunsPlugin::class,
        BroadcastPlugin::class,
        ActorsPlugin::class,
        TokenAuthPlugin::class,
        RedisPlugin::class,
        S3Plugin::class,
        ClockwerkPlugin::class,
        OpenApiPlugin::class,
        TidePlugin::class,
    ])
    ->withSetup(Setup::class)
    ->withRouting(routes: 'routes/web.php', commands: 'routes/console.php', health: '/up')
    ->create();
