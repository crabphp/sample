<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\Restock;
use App\Actions\SettlePayments;
use Crab\Contracts\Queue\Store;

/**
 * Checks stock, transactional orders and the queue and scheduled work
 * that follow them, without having to log in for each test.
 */
final class OrderTest extends ShopTestCase
{
    public function testAnOrderTakesStockAndItsWorkIsDoneByTheQueue(): void
    {
        $this->db->table('products')->where('id', 1)->update(['stock' => 5]);
        $user = $this->user('ada@example.com');
        $ada = $this->api->actingAs($user);

        $placed = $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 2], ['productId' => 2], ['productId' => 1, 'quantity' => 1]]])->assertStatus(201);
        $order = $placed->json();
        // Three mugs at 14.00 and one kettle at 62.00.
        self::assertSame(['pending', 3 * 1400 + 6200], [$placed->json('status'), $placed->json('total')]);
        $placed->assertHeader('Location', "/orders/{$order['id']}")->assertHeader('Content-Type', 'application/json');
        self::assertSame($user->id, (int) $this->db->table('orders')->where('id', $order['id'])->value('user_id'));
        self::assertSame(2, (int) $this->db->table('products')->where('id', 1)->value('stock'));

        // More than there is: refused, and nothing of the order is kept.
        $orders = $this->db->table('orders')->count();
        $this->db->table('products')->where('id', 2)->update(['stock' => 9]);
        $ada->post('/orders', ['lines' => [['productId' => 2], ['productId' => 1, 'quantity' => 3]]])->assertStatus(409);
        // Neither the order nor what its other lines had already taken: the kettle is back on the shelf.
        self::assertSame([$orders, 2, 9], [$this->db->table('orders')->count(), (int) $this->db->table('products')->where('id', 1)->value('stock'), (int) $this->db->table('products')->where('id', 2)->value('stock')]);
        $ada->post('/orders', ['lines' => []])->assertStatus(422);
        $ada->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 99]]])->assertStatus(422);

        // The event's listener queued a confirmation on "mail" and the warehouse on "default".
        $queue = $this->app->container->get(Store::class);
        self::assertSame([1, 1], [$queue->size('mail'), $queue->size('default')]);
        self::assertSame(0, $this->command('queue:work', '--stop-when-empty'));
        self::assertSame([0, 0], [$queue->size('mail'), $queue->size('default')]);
        $mail = glob($this->directory . '/mail/*.txt') ?: [];
        self::assertCount(1, $mail);
        $message = (string) file_get_contents($mail[0]);
        self::assertStringContainsString('To: ada@example.com', $message);
        self::assertStringContainsString("Subject: Your order #{$order['id']}", $message);
        self::assertStringContainsString('3 x Stoneware Mug  $42.00', $message);
        self::assertStringContainsString('Total: $104.00', $message);
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
        $old = date('Y-m-d H:i:s', time() - 3600);
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
}
