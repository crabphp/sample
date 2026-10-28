<?php

declare(strict_types=1);

namespace App;

/**
 * An order as a list of them shows it: what ListMyOrders reads, and no
 * more. "items" is not a column of orders but a count the query selects.
 */
final readonly class OrderSummary
{
    public function __construct(
        public int $id,
        public OrderStatus $status,
        public int $total,
        public ?\DateTimeImmutable $created_at,
        public int $items,
    ) {
    }
}
