<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\SendOrderConfirmation;
use Crab\Contracts\Auth\Tickets;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Queue\Queue;
use Crab\Contracts\Queue\Store;
use Crab\Contracts\Runs\Runs;

final class SeedTest extends ShopTestCase
{
    public function testFreshFixturesRevokeCredentialsBeforeReusingAnIdentity(): void
    {
        // This id will belong to the fixture administrator after the reset.
        $this->db->table('users')->where('id', 1)->update(['role' => 'member']);
        $user = $this->user('admin@example.com');
        $token = $this->app->container->get(TokenStore::class)->issue($user);
        $tickets = $this->app->container->get(Tickets::class);
        $ticket = $tickets->issue($user);

        self::assertSame(0, $this->command('shop:seed', '--fresh', '--users=2', '--products=1', '--orders=1'));

        $this->api->withToken($token)->get('/api/me')->assertStatus(401);
        self::assertNull($tickets->redeem($ticket));
        self::assertSame('admin', $this->db->table('users')->where('id', 1)->value('role'));
    }

    public function testFreshFixturesDiscardWorkForThePreviousShop(): void
    {
        $this->api
            ->actingAs($this->user('ada@example.com'))
            ->post('/api/orders', [
                'lines' => [['productId' => 1]],
            ])
            ->assertStatus(201);
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        $run = $this->db->table('runs')->value('id');
        self::assertNotNull($run);
        $this->app->container->get(Runs::class)->signal($run, 'note', 'Previous shop');

        $queue = $this->app->container->get(Queue::class);
        $store = $this->app->container->get(Store::class);
        $now = $this->clock->now()->getTimestamp();
        $queue->on('mail')->push(SendOrderConfirmation::class, orderId: 1);
        $failed = $store->reserve(['mail'], $now, 90);
        self::assertNotNull($failed);
        $store->fail($failed, 'Refused by the mail server.', $now);
        $queue->on('mail')->later(60, SendOrderConfirmation::class, orderId: 1);
        $queue->on('warehouse')->push(SendOrderConfirmation::class, orderId: 1);

        self::assertSame(0, $this->command('shop:seed', '--fresh', '--users=2', '--products=1', '--orders=1'));

        self::assertSame(0, $store->size('mail'));
        self::assertSame(0, $store->size('warehouse'));
        self::assertSame([], $store->failed());
        self::assertSame(0, $this->db->table('runs')->count());
        self::assertSame(0, $this->db->table('run_steps')->count());
    }
}
