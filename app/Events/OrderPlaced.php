<?php

declare(strict_types=1);

namespace App\Events;

/**
 * An order was placed and saved. What follows from that, a confirmation,
 * the warehouse being told, is done by listeners, so that placing an
 * order does not have to know of each.
 */
final readonly class OrderPlaced
{
    /**
     * @param list<int> $productIds The products in it.
     */
    public function __construct(public int $orderId, public int|string $userId, public int $total, public array $productIds)
    {
    }
}
