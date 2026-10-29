<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\OrderPlaced;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Queue\Queue;
use Crab\Contracts\Runs\Runs;

/**
 * Hands what an order needs done, and what the customer should not wait
 * for, to the queue: the confirmation on the mail queue, the warehouse
 * on its own. A run follows the order up over the days after.
 */
final readonly class QueueOrderWork
{
    public function __construct(
        private Queue $queue,
        private Runs $runs,
        private Database $db,
    ) {
    }

    public function handle(OrderPlaced $event): void
    {
        $this->queue->on('mail')->push(SendOrderConfirmation::class, orderId: $event->orderId);
        $this->queue->on('warehouse')->push(SyncInventory::class, orderId: $event->orderId, productIds: $event->productIds);

        $run = $this->runs->start(FollowUpOrder::class, orderId: $event->orderId);
        $this->db->table('orders')->where('id', $event->orderId)->update(['follow_up_run' => $run]);
    }
}
