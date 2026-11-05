<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Order;
use App\OrderStatus;
use Crab\Contracts\Broadcast\Broadcaster;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Queue\Queue;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;
use Psr\Clock\ClockInterface;

/**
 * An administrator marks a paid order shipped. Its owner hears the new
 * status only after the transaction commits, and the run following the
 * order up hears that it has shipped.
 */
final readonly class ShipOrder
{
    public function __construct(
        private Database $db,
        private Broadcaster $broadcast,
        private ClockInterface $clock,
        private Queue $queue,
    ) {}

    /**
     * @return array{id: int, status: string}
     */
    #[Response(200, body: OpenApiSchemas::SHIPPED_ORDER)]
    #[Response(404, description: 'No such order.')]
    #[Response(409, description: 'Only a paid order can be shipped.')]
    public function handle(int $id): array
    {
        $this->db->transaction(function (Connection $transaction) use ($id): void {
            $orders = $transaction->table('orders')->into(Order::class);
            /** @var Order $order */
            $order = (clone $orders)->where('id', $id)->lockForUpdate()->first() ?? throw new HttpException(
                404,
                'No such order.',
            );

            if ($order->status === OrderStatus::Shipped) {
                // A retry may repair a lost notification, without shipping or broadcasting twice.
                $this->queue->within($transaction)->push(SignalOrderShipped::class, orderId: $id);
                return;
            }
            if ($order->status !== OrderStatus::Paid) {
                throw new HttpException(409, 'Only a paid order can be shipped. Wait for payment before shipping it.');
            }

            // Through the record's map: the enum is written as its value, the date as the column holds one.
            $version = $order->version + 1;
            $orders
                ->where('id', $id)
                ->update([
                    'status' => OrderStatus::Shipped,
                    'version' => $version,
                    'updated_at' => $this->clock->now(),
                ]);

            // On the database queue this commits with the shipment. A failed signal is retried as a job.
            $this->queue->within($transaction)->push(SignalOrderShipped::class, orderId: $id);

            $this->broadcast
                ->within($transaction)
                ->to("orders.{$id}")
                ->send([
                    'type' => 'status',
                    'id' => $id,
                    'status' => 'shipped',
                    'version' => $version,
                ]);
        });

        return ['id' => $id, 'status' => 'shipped'];
    }
}
