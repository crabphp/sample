<?php

declare(strict_types=1);

namespace App;

/**
 * One row of the orders table, read with ->into(Order::class): the status
 * as an enum and the times as dates, each checked as it is read.
 */
final readonly class Order
{
    public function __construct(
        public int $id,
        public int $user_id,
        public OrderStatus $status,
        // In cents.
        public int $total,
        // Counts every change, so that a client can tell which snapshot is newer.
        public int $version,
        public ?\DateTimeImmutable $created_at,
        public ?\DateTimeImmutable $updated_at,
    ) {
    }
}
