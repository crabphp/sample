<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Restock;
use App\Actions\SettlePayments;
use App\Events\OrderPlaced;
use App\Actions\BuildDailyReport;
use App\Actions\ForgetProductCache;
use App\Actions\QueueOrderWork;
use App\FileMailer;
use App\Mailer;
use App\Users;
use Crab\Contracts\Auth\IdentityResolver;
use Crab\Contracts\Container\Container;
use Crab\Contracts\Events\Dispatcher;
use Crab\Contracts\Http\MiddlewareStack;
use Crab\Contracts\Provider;
use Crab\Contracts\Schedule\Schedule;
use Crab\Http\Concurrency;

/**
 * Where the shop's own services are bound, and where it says what
 * follows from an event and what runs by the clock.
 */
final class AppProvider implements Provider
{
    public function register(Container $container): void
    {
        $container->singleton(IdentityResolver::class, Users::class);
        $container->singleton(Mailer::class, static fn (): Mailer => new FileMailer((string) config('mail.path'), (float) config('mail.fail_rate')));

        $limit = config('shop.max_requests');
        if ($limit !== null) {
            $container->get(MiddlewareStack::class)->add(new Concurrency($limit));
        }

        $events = $container->get(Dispatcher::class);
        $events->listen(OrderPlaced::class, QueueOrderWork::class);
        $events->listen(OrderPlaced::class, ForgetProductCache::class);

        $schedule = $container->get(Schedule::class);
        // Every ten seconds: closer than cron can come.
        $schedule->call(SettlePayments::class)->everySeconds(10)->name('settle-payments');
        $schedule->command('orders:cancel-stale')->everyMinute();
        $schedule->call(Restock::class)->everyMinute()->name('restock');
        $schedule->job(BuildDailyReport::class)->daily('06:00')->name('daily-report');
    }
}
