<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Models\Order;
use App\OrderStatus;
use Crab\Contracts\Broadcast\Broadcaster;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Queue\Queue;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;

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
            // A model call runs in the transaction it is written in, with no connection to pass.
            $order = Order::query()->lockForUpdate()->find($id) ?? throw new HttpException(404, 'No such order.');

            if ($order->status === OrderStatus::Shipped) {
                // A retry may repair a lost notification, without shipping or broadcasting twice.
                $this->queue->within($transaction)->push(SignalOrderShipped::class, orderId: $id);
                return;
            }
            if ($order->status !== OrderStatus::Paid) {
                throw new HttpException(409, 'Only a paid order can be shipped. Wait for payment before shipping it.');
            }

            // Only the status and the version are written, and the time Tide keeps.
            $order->status = OrderStatus::Shipped;
            ++$order->version;
            $order->save();

            // On the database queue this commits with the shipment. A failed signal is retried as a job.
            $this->queue->within($transaction)->push(SignalOrderShipped::class, orderId: $id);

            $this->broadcast
                ->within($transaction)
                ->to("orders.{$id}")
                ->send([
                    'type' => 'status',
                    'id' => $id,
                    'status' => 'shipped',
                    'version' => $order->version,
                ]);
        });

        return ['id' => $id, 'status' => 'shipped'];
    }
}
