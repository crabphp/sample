<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Queue\Store;
use Crab\Contracts\Schedule\Schedule;
use Crab\Schedule\Scheduler;

final class ScheduleTest extends ShopTestCase
{
    public function testServersSharingTheCacheDoNotRepeatADeliveryOrReport(): void
    {
        $schedule = $this->app->container->get(Schedule::class);
        $cache = $this->app->container->get(Cache::class);
        $first = new Scheduler($schedule, $this->app->container, clock: $this->clock, cache: $cache);
        $second = new Scheduler($schedule, $this->app->container, clock: $this->clock, cache: $cache);
        $tasks = [];
        foreach ($schedule->tasks() as $task) {
            $tasks[$task->named()] = $task;
        }
        $minute = $this->clock->now()->setTime(6, 0);
        $this->db->table('products')->where('id', 1)->update(['stock' => 0]);

        self::assertTrue($first->run($tasks['restock'], $minute));
        self::assertSame(60, (int) $this->db->table('products')->where('id', 1)->value('stock'));
        // Customers empty the shelf before the second server reaches the same minute's task.
        $this->db->table('products')->where('id', 1)->update(['stock' => 0]);
        $second->run($tasks['restock'], $minute);
        self::assertSame(0, (int) $this->db->table('products')->where('id', 1)->value('stock'));

        self::assertTrue($first->run($tasks['daily-report'], $minute));
        $second->run($tasks['daily-report'], $minute);
        $jobs = $this->app->container->get(Store::class);
        self::assertCount(1, $jobs->jobs('default', 'pending', $this->clock->now()->getTimestamp()));

        // A later delivery still runs, whichever server reaches that minute first.
        self::assertTrue($second->run($tasks['restock'], $minute->modify('+1 minute')));
        self::assertSame(60, (int) $this->db->table('products')->where('id', 1)->value('stock'));
    }
}
