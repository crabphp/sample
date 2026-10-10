<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\OrderPlaced;
use App\User;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Length;
use Crab\Validation\Range;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * Places an order: takes each product out of stock and saves the order
 * and its lines, all or nothing. What follows from an order is left to
 * whoever listens for OrderPlaced.
 */
final readonly class PlaceOrder
{
    public function __construct(private Database $db, private EventDispatcherInterface $events, private LoggerInterface $log)
    {
    }

    public function handle(
        User $user,
        // What a customer sends, described here in place of a class written for it.
        #[Fields([
            'lines' => [['productId' => 'int', 'quantity' => ['?int', new Range(min: 1, max: 20)]], new Length(min: 1, max: 20)],
        ])]
        Data $order,
    ): Response {
        // The same product on two lines is one line of both quantities.
        $wanted = [];
        foreach ($order->lines as $line) {
            // One, when the line does not say how many.
            $wanted[$line->productId] = ($wanted[$line->productId] ?? 0) + ($line->quantity ?? 1);
        }
        // Always in the same order, so that two orders for the same products never wait on each other.
        ksort($wanted);
        [$orderId, $total] = $this->db->transaction(static function (Connection $db) use ($user, $wanted): array {
            $now = date('Y-m-d H:i:s');
            $total = 0;
            $lines = [];
            foreach ($wanted as $productId => $quantity) {
                $product = $db->table('products')->where('id', $productId)->lockForUpdate()->first();
                if ($product === null) {
                    throw new HttpException(422, "There is no product {$productId}.");
                }
                // Only while there is enough: of two orders for the last one, the second finds none left.
                $taken = $db->table('products')->where('id', $productId)->where('stock', '>=', $quantity)->decrement('stock', $quantity, ['updated_at' => $now]);
                if ($taken === 0) {
                    throw new HttpException(409, "There is not enough of {$product['name']} in stock.");
                }
                $total += (int) $product['price'] * $quantity;
                $lines[] = ['product_id' => $productId, 'quantity' => $quantity, 'price' => (int) $product['price']];
            }
            $orderId = (int) $db->table('orders')->insert(['user_id' => $user->id, 'status' => 'pending', 'total' => $total, 'created_at' => $now, 'updated_at' => $now]);
            $db->table('order_items')->insertMany(array_map(static fn (array $line): array => ['order_id' => $orderId, ...$line], $lines));
            return [$orderId, $total];
        });
        // After the transaction: listeners must find the order saved. And whatever becomes of them, the
        // order is placed: a queue that is down must not have the customer told it failed, and order again.
        try {
            $this->events->dispatch(new OrderPlaced($orderId, $user->id, $total, array_keys($wanted)));
        } catch (\Throwable $error) {
            $this->log->error('Order {order} was placed, and what follows from it could not be started: {message}', ['order' => $orderId, 'message' => $error->getMessage(), 'exception' => $error]);
        }
        return Response::created(['id' => $orderId, 'status' => 'pending', 'total' => $total], "/orders/{$orderId}");
    }
}
