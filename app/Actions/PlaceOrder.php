<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Http\OpenApiSchemas;
use App\Http\OrderInput;
use App\Models\Order;
use App\Models\OrderItem;
use App\OrderStatus;
use App\User;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
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
    ) {}

    #[ApiResponse(201, body: OpenApiSchemas::CREATED_ORDER, description: 'Order placed.', headers: ['Location'])]
    #[ApiResponse(409, description: 'There is not enough stock to place the order.')]
    public function handle(User $user, OrderInput $order): Response
    {
        $placed = $this->db->transaction(fn(Connection $db): array => $this->within($db, $user, $order));
        $this->announce($user, $placed);
        return Response::created($placed, "/api/orders/{$placed['id']}");
    }

    /** Uses the caller's connection so cart consumption and order creation commit together. */
    public function within(Connection $db, User $user, OrderInput $order): array
    {
        $quantities = [];
        foreach ($order->lines as $line) {
            $quantities[$line->productId] = ($quantities[$line->productId] ?? 0) + ($line->quantity ?? 1);
        }
        ksort($quantities);
        return $this->createOrder($db, $user, $quantities);
    }

    /** Called after the transaction releases its connection, including for SQLite pools of one. */
    public function announce(User $user, array $placed): void
    {
        try {
            $productIds = array_map(
                intval(...),
                OrderItem::query()->where('order_id', $placed['id'])->pluck('product_id'),
            );
            $this->events->dispatch(new OrderPlaced($placed['id'], $user->id, $placed['total'], $productIds));
        } catch (Throwable $error) {
            $this->log->error('Order {order} was placed, but its follow-up work failed: {message}', [
                'order' => $placed['id'],
                'message' => $error->getMessage(),
                'exception' => $error,
            ]);
        }
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
            $product = $db
                ->table('products')
                ->where('id', $productId)
                ->lockForUpdate()
                ->first() ?? throw new HttpException(422, "There is no product {$productId}.");

            $taken = $db
                ->table('products')
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

        // Models in the transaction $db is: they are written, or undone, with the stock taken above.
        $placed = new Order();
        $placed->user_id = (int) $user->id;
        $placed->status = OrderStatus::Pending;
        $placed->total = $total;
        $placed->save();

        foreach ($lines as $line) {
            $item = new OrderItem();
            $item->order_id = $placed->id;
            $item->product_id = $line['product_id'];
            $item->quantity = $line['quantity'];
            $item->price = $line['price'];
            $item->save();
        }

        return ['id' => $placed->id, 'status' => $placed->status->value, 'total' => $total];
    }
}
