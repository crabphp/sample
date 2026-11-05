<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Contracts\Runs\Runs;

/**
 * Delivers the shipment to its run after the transaction commits. The
 * queue retries a failed delivery; a repeated signal keeps its first value.
 */
final readonly class SignalOrderShipped
{
    public function __construct(
        private Database $db,
        private Runs $runs,
    ) {}

    public function handle(int $orderId): void
    {
        $order = $this->db->table('orders')->find($orderId);
        if ($order === null || $order['status'] !== 'shipped' || $order['follow_up_run'] === null) {
            return;
        }
        $at = new \DateTimeImmutable($order['updated_at']);
        $this->runs->signal((int) $order['follow_up_run'], 'shipped', ['at' => $at->format(DATE_ATOM)]);
    }
}
