<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Restock;
use App\Actions\SettlePayments;
use App\Events\OrderPlaced;
use Crab\Contracts\Events\Dispatcher;
use Crab\Contracts\Queue\Store;
use Crab\Contracts\Runs\Runs;
use Crab\Contracts\Runs\Status;

/**
 * Checks stock, transactional orders and the queue and scheduled work
 * that follow them, without having to log in for each test.
 */
final class OrderTest extends ShopTestCase
{
    public function testYourOrdersArePagedByACursorThatAnOrderPlacedMeanwhileDoesNotShift(): void
    {
        $at = ['created_at' => '2026-01-02 12:00:00', 'updated_at' => '2026-01-02 12:00:00'];
        foreach (range(10, 21) as $id) {
            $this->db->table('orders')->insert(['id' => $id, 'user_id' => 2, 'status' => 'paid', 'total' => 1400, ...$at]);
            $this->db->table('order_items')->insertMany([
                ['order_id' => $id, 'product_id' => 1, 'quantity' => 2, 'price' => 700],
                ['order_id' => $id, 'product_id' => 3, 'quantity' => 1, 'price' => 0],
            ]);
        }
        $ada = $this->api->actingAs($this->user('ada@example.com'));

        $first = $ada->get('/orders')->assertStatus(200);
        self::assertSame(range(21, 12, -1), array_column($first->json('data'), 'id'));
        self::assertSame([3, true], [$first->json('data.0.items'), $first->json('has_more')]);

        // Placed between the two pages, it is newer than where the first ended, so it is on neither.
        $this->db->table('orders')->insert(['id' => 30, 'user_id' => 2, 'status' => 'pending', 'total' => 1400, ...$at]);
        $second = $ada->get('/orders', ['cursor' => $first->json('next_cursor')])->assertStatus(200);
        self::assertSame([11, 10, 1], array_column($second->json('data'), 'id'));
        self::assertSame([1, false, null], [$second->json('data.2.items'), $second->json('has_more'), $second->json('next_cursor')]);

        $ada->get('/orders', ['cursor' => 'made-up'])->assertStatus(422);
    }

    public function testAnOrderTakesStockAndItsWorkIsDoneByTheQueue(): void
    {
        $this->db->table('products')->where('id', 1)->update(['stock' => 5]);
        $user = $this->user('ada@example.com');
        $ada = $this->api->actingAs($user);

        // Missing and explicit-null quantities each mean one.
        $placed = $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 2], ['productId' => 2], ['productId' => 1, 'quantity' => null]]])->assertStatus(201);
        $order = $placed->json();
        // Three mugs at 14.00 and one kettle at 62.00.
        self::assertSame(['pending', 3 * 1400 + 6200], [$placed->json('status'), $placed->json('total')]);
        $placed->assertHeader('Location', "/orders/{$order['id']}")->assertHeader('Content-Type', 'application/json');
        self::assertSame($user->id, (int) $this->db->table('orders')->where('id', $order['id'])->value('user_id'));
        self::assertSame(2, (int) $this->db->table('products')->where('id', 1)->value('stock'));
        self::assertSame([3, 1], $this->db->table('order_items')->where('order_id', $order['id'])->orderBy('product_id')->pluck('quantity'));
        $ada->post('/orders', ['lines' => []])->assertStatus(422);
        $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 99]]])->assertStatus(422);

        // The event's listener queued a confirmation on "mail" and the warehouse on "default".
        $queue = $this->app->container->get(Store::class);
        self::assertSame([1, 1], [$queue->size('mail'), $queue->size('warehouse')]);
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        self::assertSame([0, 0], [$queue->size('mail'), $queue->size('warehouse')]);
        $mail = glob($this->directory . '/mail/*.txt') ?: [];
        self::assertCount(1, $mail);
        self::assertStringStartsWith('20260201-120000-', basename($mail[0]));
        $message = (string) file_get_contents($mail[0]);
        self::assertStringContainsString('To: ada@example.com', $message);
        self::assertStringContainsString("Subject: Your order #{$order['id']}", $message);
        self::assertStringContainsString('3 x Stoneware Mug  $42.00', $message);
        self::assertStringContainsString('Total: $104.00', $message);
    }

    public function testAnOrderIsFollowedUpByARunThatWaitsForItToShipAndThenForItToArrive(): void
    {
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $id = $ada->post('/orders', ['lines' => [['productId' => 3]]])->assertStatus(201)->json('id');
        $runs = $this->app->container->get(Runs::class);
        $run = (int) $this->db->table('orders')->where('id', $id)->value('follow_up_run');

        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        self::assertSame(Status::Waiting, $runs->find($run)?->status, 'It waits for the order to ship, holding no worker.');

        $this->db->table('orders')->where('id', $id)->update(['status' => 'paid']);
        // Back to serving HTTP after the worker command, so that shipping's broadcast is heard.
        $this->app->container->get(\Crab\Broadcast\Lifecycle::class)->serving = true;
        $this->api->actingAs($this->user('admin@example.com'))->put("/orders/{$id}/ship")->assertStatus(200);
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        self::assertSame(Status::Sleeping, $runs->find($run)?->status, 'Shipped, it gives the order three days to arrive.');

        $this->clock->advance('+3 days');
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        $done = $runs->find($run);
        self::assertSame(Status::Completed, $done?->status);
        self::assertSame(['order' => $id, 'shipped' => '2026-02-01T12:00:00+00:00', 'asked' => true], $done?->result);
        self::assertSame(['shipped', 'arrival', 'ask-for-review'], $done?->steps);
        $subjects = array_map(
            static fn(string $file): string => (string) preg_replace('/.*^Subject: (.*?)$.*/ms', '$1', (string) file_get_contents($file)),
            glob($this->directory . '/mail/*.txt') ?: [],
        );
        self::assertContains("How is your order #{$id}?", $subjects);
    }

    public function testAFailedLaterLineRestoresTheStockAlreadyTaken(): void
    {
        $this->db->table('products')->where('id', 1)->update(['stock' => 5]);
        $this->db->table('products')->where('id', 2)->update(['stock' => 0]);
        $orders = $this->db->table('orders')->count();
        $items = $this->db->table('order_items')->count();
        $ada = $this->api->actingAs($this->user('ada@example.com'));

        // Products are locked by id: both failures follow the successful
        // decrement of product 1, so the transaction must restore it.
        foreach ([2 => 409, 99999 => 422] as $product => $status) {
            $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 2], ['productId' => $product]]])->assertStatus($status);
            self::assertSame(5, (int) $this->db->table('products')->where('id', 1)->value('stock'));
            self::assertSame($orders, $this->db->table('orders')->count());
            self::assertSame($items, $this->db->table('order_items')->count());
        }
        $queue = $this->app->container->get(Store::class);
        self::assertSame([0, 0], [$queue->size('mail'), $queue->size('warehouse')]);
    }

    public function testAListenerFailureDoesNotTurnACommittedOrderIntoAnError(): void
    {
        $stock = (int) $this->db->table('products')->where('id', 1)->value('stock');
        $observed = null;
        $this->app->container->get(Dispatcher::class)->listen(OrderPlaced::class, function (OrderPlaced $event) use (&$observed): void {
            $observed = $this->db->table('orders')->find($event->orderId);
            throw new \RuntimeException('The notification service is unavailable.');
        });

        $placed = $this->api->actingAs($this->user('ada@example.com'))
            ->post('/orders', ['lines' => [['productId' => 1]]])->assertStatus(201);
        self::assertSame($placed->json('id'), (int) $observed['id']);
        self::assertSame('pending', $observed['status']);
        self::assertSame($stock - 1, (int) $this->db->table('products')->where('id', 1)->value('stock'));
        self::assertStringContainsString('The notification service is unavailable.', (string) file_get_contents($this->directory . '/app.log'));
    }

    public function testOrdersArePaidCancelledAndTheShelvesRefilledByTheClock(): void
    {
        $this->db->table('products')->where('id', 1)->update(['stock' => 5]);
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $ids = [];
        for ($i = 0; $i < 12; ++$i) {
            $ids[] = $ada->post('/orders', ['lines' => [['productId' => 3]]])->assertStatus(201)->json('id');
        }
        self::assertCount(12, $ids);
        $old = $this->clock->now()->modify('-1 hour')->format('Y-m-d H:i:s');
        $this->db->table('orders')->whereIn('id', $ids)->update(['created_at' => $old]);
        $stock = (int) $this->db->table('products')->where('id', 3)->value('stock');

        // What the scheduler runs every ten seconds: all are paid but the one in ten that never is.
        $this->app->container->get(SettlePayments::class)->handle();
        $unpaid = $this->db->table('orders')->whereIn('id', $ids)->where('status', 'pending')->pluck('id');
        self::assertSame(array_values(array_filter($ids, static fn (int $id): bool => $id % 10 === 0)), $unpaid);
        self::assertNotSame([], $unpaid);

        // Every minute: those are cancelled, and what they took is back in stock.
        self::assertSame(0, $this->command('orders:cancel-stale'));
        self::assertSame(count($unpaid), $this->db->table('orders')->whereIn('id', $ids)->where('status', 'cancelled')->count());
        self::assertSame($stock + count($unpaid), (int) $this->db->table('products')->where('id', 3)->value('stock'));

        $this->app->container->get(Restock::class)->handle();
        self::assertSame(65, (int) $this->db->table('products')->where('id', 1)->value('stock'));

        self::assertSame(0, $this->command('schedule:list'));
    }

    public function testCancellingSharedProductsRestoresOnlyStaleOrdersOnce(): void
    {
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $stale = [];
        foreach ([2, 3] as $quantity) {
            $stale[] = $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => $quantity]]])->assertStatus(201)->json('id');
        }
        $recent = $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 4]]])->assertStatus(201)->json('id');
        $this->db->table('orders')->whereIn('id', $stale)->update([
            'created_at' => $this->clock->now()->modify('-1 hour')->format('Y-m-d H:i:s'),
        ]);
        $stock = (int) $this->db->table('products')->where('id', 1)->value('stock');

        self::assertSame(0, $this->command('orders:cancel-stale'));
        self::assertSame($stock + 5, (int) $this->db->table('products')->where('id', 1)->value('stock'));
        self::assertSame(2, $this->db->table('orders')->whereIn('id', $stale)->where('status', 'cancelled')->count());
        self::assertSame('pending', $this->db->table('orders')->where('id', $recent)->value('status'));
        self::assertSame('paid', $this->db->table('orders')->where('id', 1)->value('status'), 'Historical paid orders stay paid and keep their stock.');

        self::assertSame(0, $this->command('orders:cancel-stale'));
        self::assertSame($stock + 5, (int) $this->db->table('products')->where('id', 1)->value('stock'), 'Cancelling again does not return the same stock twice.');
    }
}
