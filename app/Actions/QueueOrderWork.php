<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\OrderPlaced;
use App\Actions\SendOrderConfirmation;
use App\Actions\SyncInventory;
use Crab\Contracts\Queue\Queue;

/**
 * Hands what an order needs done, and what the customer should not wait
 * for, to the queue: the confirmation on the mail queue, the warehouse
 * on the default one.
 */
final readonly class QueueOrderWork
{
    public function __construct(private Queue $queue)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        $this->queue->on('mail')->push(SendOrderConfirmation::class, orderId: $event->orderId);
        $this->queue->push(SyncInventory::class, orderId: $event->orderId, productIds: $event->productIds);
    }
}
