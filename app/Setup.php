<?php

declare(strict_types=1);

namespace App;

use App\Actions\BuildDailyReport;
use App\Actions\ForgetProductCache;
use App\Actions\QueueOrderWork;
use App\Actions\Restock;
use App\Actions\SettlePayments;
use App\Events\OrderPlaced;
use App\Http\ShopErrors;
use Crab\Auth\CookieAuth;
use Crab\Contracts\Auth\IdentityResolver;
use Crab\Contracts\Config\Config;
use Crab\Contracts\Container\Container;
use Crab\Contracts\Events\Dispatcher;
use Crab\Contracts\Http\ErrorRenderer;
use Crab\Contracts\Http\MiddlewareStack;
use Crab\Contracts\Plugin;
use Crab\Contracts\Routing\Router;
use Crab\Contracts\Schedule\Schedule;
use Crab\Contracts\Servers;
use Crab\Http\Concurrency;
use Crab\OpenApi\DocumentBuilder;
use Psr\Clock\ClockInterface;

/**
 * Where the shop's own services are bound, and where it says what
 * follows from an event and what runs by the clock.
 */
final class Setup implements Plugin
{
    public function register(Container $container): void
    {
        $config = $container->get(Config::class);
        $config->defaults(
            'shop',
            [
                'login_limit' => 10,
                'register_limit' => 10,
                'max_requests' => null,
                'report_disk' => 'reports',
                'catalogue' => storage_path('catalogue.csv'),
                'warehouse_url' => 'http://127.0.0.1:8080',
                'model_url' => 'http://127.0.0.1:8080',
                'settle_after' => 5,
                'cancel_after' => 2,
            ],
            [
                'login_limit' => ['type' => 'integer', 'minimum' => 1],
                'register_limit' => ['type' => 'integer', 'minimum' => 1],
                'max_requests' => ['type' => ['integer', 'null'], 'minimum' => 1],
                'report_disk' => ['type' => 'string', 'minLength' => 1],
                'catalogue' => ['type' => 'string', 'minLength' => 1],
                'warehouse_url' => ['type' => 'string'],
                'model_url' => ['type' => 'string', 'minLength' => 1],
                'settle_after' => ['type' => 'integer', 'minimum' => 0],
                'cancel_after' => ['type' => 'integer', 'minimum' => 1],
            ],
        );
        $config->defaults(
            'mail',
            ['path' => storage_path('mail'), 'fail_rate' => 0.15],
            [
                'path' => ['type' => 'string', 'minLength' => 1],
                'fail_rate' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
            ],
        );
        // These settings construct middleware while the routes are declared, before runtime().
        $config->check('shop', 'mail');
        $container->singleton(IdentityResolver::class, Users::class);
        $container->singleton(ErrorRenderer::class, ShopErrors::class);
        $container->singleton(
            Mailer::class,
            static fn(Container $container): Mailer => new FileMailer(
                directory: (string) config('mail.path'),
                clock: $container->get(ClockInterface::class),
                failRate: (float) config('mail.fail_rate'),
            ),
        );
        $container->singleton(DocumentBuilder::class, static function (Container $container): DocumentBuilder {
            $config = $container->get(Config::class);
            $config->check('openapi');
            // Resolve auth only for generation, after all application bindings are in place.
            $cookie = $container->get(CookieAuth::class)->cookie('');
            $security = $config->get('openapi.security', []);
            $security['cookie']['name'] = $cookie->name;
            $config->defaults('openapi', ['security' => $security]);
            return new DocumentBuilder(
                $container->get(Router::class),
                $config,
                $container->has(ErrorRenderer::class),
                $container->get(MiddlewareStack::class)->layers(),
            );
        });

        $limit = config('shop.max_requests');
        if ($limit !== null) {
            $container->get(MiddlewareStack::class)->add(new Concurrency((int) $limit));
        }

        $events = $container->get(Dispatcher::class);
        $events->listen(OrderPlaced::class, QueueOrderWork::class);
        $events->listen(OrderPlaced::class, ForgetProductCache::class);

        $schedule = $container->get(Schedule::class);
        // Every ten seconds: closer than cron can come.
        $schedule
            ->call(\App\Actions\PruneShopData::class)
            ->daily('04:00')
            ->onOneServer()
            ->name('prune-shop-data');
        $schedule->call(SettlePayments::class)->everySeconds(10)->onOneServer()->name('settle-payments');
        $schedule->command('order:cancel-stale')->everyMinute()->onOneServer();
        $schedule->call(Restock::class)->everyMinute()->onOneServer()->name('restock');
        $schedule->job(BuildDailyReport::class)->daily('06:00')->onOneServer()->name('daily-report');
        // A run whose job was lost, to a process that died between keeping its state and the push, gets one.
        $schedule->command('runs:recover')->everyMinute()->onOneServer();

        // A server kept across reloads: a deploy's `reload` leaves the dispatch desk in the middle of
        // its handoff, on the code it started with; `restart` and `stop` end it.
        $container->get(Servers::class)->command('dispatch', 'shop:dispatch', reloads: false);
    }
}
