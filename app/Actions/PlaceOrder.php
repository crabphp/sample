<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Http\OrderInput;
use App\User;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Places an order: takes each product out of stock and saves the order
 * and its lines, all or nothing. What follows from an order is left to
 * whoever listens for OrderPlaced.
 */
final readonly class PlaceOrder
{
    public function __construct(
        private Database $db,
        private EventDispatcherInterface $events,
        private LoggerInterface $log,
        private ClockInterface $clock,
    ) {
    }

    public function handle(User $user, OrderInput $order): Response
    {
        $quantities = [];
        foreach ($order->lines as $line) {
            $quantities[$line->productId] = ($quantities[$line->productId] ?? 0) + ($line->quantity ?? 1);
        }

        // Lock products in a consistent order to avoid deadlocks between overlapping orders.
        ksort($quantities);

        $placed = $this->db->transaction(
            fn (Connection $db): array => $this->createOrder($db, $user, $quantities),
        );

        // A listener failure must not invite a retry of an order that already committed.
        try {
            $this->events->dispatch(new OrderPlaced($placed['id'], $user->id, $placed['total'], array_keys($quantities)));
        } catch (Throwable $error) {
            $this->log->error('Order {order} was placed, but its follow-up work failed: {message}', [
                'order' => $placed['id'],
                'message' => $error->getMessage(),
                'exception' => $error,
            ]);
        }

        return Response::created($placed, "/orders/{$placed['id']}");
    }

    /**
     * @param array<int, int> $quantities Quantities grouped and sorted by product ID.
     * @return array{id: int, status: string, total: int}
     */
    private function createOrder(Connection $db, User $user, array $quantities): array
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $total = 0;
        $lines = [];

        foreach ($quantities as $productId => $quantity) {
            $product = $db->table('products')
                ->where('id', $productId)
                ->lockForUpdate()
                ->first() ?? throw new HttpException(422, "There is no product {$productId}.");

            $taken = $db->table('products')
                ->where('id', $productId)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity, ['updated_at' => $now]);

            if ($taken === 0) {
                throw new HttpException(409, "There is not enough of {$product['name']} in stock.");
            }

            $price = (int) $product['price'];
            $total += $price * $quantity;
            $lines[] = ['product_id' => $productId, 'quantity' => $quantity, 'price' => $price];
        }

        $id = (int) $db->table('orders')->insert([
            'user_id' => $user->id,
            'status' => 'pending',
            'total' => $total,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $db->table('order_items')->insertMany(
            array_map(static fn (array $line): array => ['order_id' => $id, ...$line], $lines),
        );

        return ['id' => $id, 'status' => 'pending', 'total' => $total];
    }
}
