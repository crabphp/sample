<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\QueueOrderWork;
use App\Events\OrderPlaced;
use Crab\Contracts\Queue\Queue;
use Crab\Contracts\Runs\Runs;
use Crab\Contracts\Runs\Status;

final class ShippingTest extends ShopTestCase
{
    public function testShippingQueuesItsSignalInsteadOfFailingAfterTheCommit(): void
    {
        $id = $this->api
            ->actingAs($this->user('ada@example.com'))
            ->post('/api/orders', [
                'lines' => [['productId' => 1]],
            ])
            ->assertStatus(201)
            ->json('id');
        $this->db->table('orders')->where('id', $id)->update(['status' => 'paid']);
        $run = $this->db->table('orders')->where('id', $id)->value('follow_up_run');
        $runs = $this->app->container->get(Runs::class);
        $unavailable = $this->createStub(Runs::class);
        $unavailable->method('signal')->willThrowException(new \RuntimeException('The run store is unavailable.'));
        $this->app->container->instance(Runs::class, $unavailable);

        $this->api->actingAs($this->user('admin@example.com'))->put("/api/orders/{$id}/ship")->assertStatus(200);

        self::assertSame('shipped', $this->db->table('orders')->where('id', $id)->value('status'));
        $this->app->container->instance(Runs::class, $runs);
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        self::assertSame(Status::Sleeping, $runs->find($run)?->status);
    }

    public function testAnUnstoredSignalRollsBackShippingWithTheDatabaseQueue(): void
    {
        $queue = $this->createStub(Queue::class);
        $queue->method('within')->willReturnSelf();
        $queue->method('push')->willThrowException(new \RuntimeException('The job could not be stored.'));
        $this->app->container->instance(Queue::class, $queue);

        $this->api->actingAs($this->user('admin@example.com'))->put('/api/orders/1/ship')->assertStatus(500);

        self::assertSame('paid', $this->db->table('orders')->where('id', 1)->value('status'));
        self::assertSame(1, $this->db->table('orders')->where('id', 1)->value('version'));
    }

    public function testARunLinkedAfterShippingStillHearsTheShipment(): void
    {
        $this->api->actingAs($this->user('admin@example.com'))->put('/api/orders/1/ship')->assertStatus(200);
        // The order committed, but its listener was delayed until after shipping.
        $this->app->container->get(QueueOrderWork::class)->handle(new OrderPlaced(1, 2, 1400, [1]));
        $run = $this->db->table('orders')->where('id', 1)->value('follow_up_run');

        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));

        self::assertSame(Status::Sleeping, $this->app->container->get(Runs::class)->find($run)?->status);
    }

    public function testRetryingShippingKeepsItsOriginalVersionAndTime(): void
    {
        $admin = $this->api->actingAs($this->user('admin@example.com'));
        $admin->put('/api/orders/1/ship')->assertStatus(200);
        $before = $this->db->table('orders')->find(1);
        $this->clock->advance('+1 hour');

        $admin->put('/api/orders/1/ship')->assertStatus(200);

        self::assertSame($before, $this->db->table('orders')->find(1));
    }
}
