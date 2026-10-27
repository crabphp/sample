<?php

declare(strict_types=1);

namespace Tests;

/**
 * Checks that the dashboard page is public but its state needs its token.
 */
final class DashboardTest extends ShopTestCase
{
    public function testTheDashboardIsThereAndNeedsItsToken(): void
    {
        $this->api->get('/products')->assertStatus(200);
        $this->api->get('/clockwerk')->assertStatus(200);
        $this->api->get('/clockwerk/api')->assertStatus(401);
        $state = $this->api->get('/clockwerk/api', ['token' => 'test-token'])->assertStatus(200)->assertJson('app', 'Crab Shop');
        self::assertSame(['mail', 'warehouse', 'default'], array_column($state->json('jobs.supervisors'), 'name'));
        self::assertSame(['settle-payments', 'command orders:cancel-stale', 'restock', 'daily-report'], array_column($state->json('tasks.rows'), 'name'));
    }
}
