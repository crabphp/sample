<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Broadcast\Broadcaster;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * Marks the orders whose payment has come through as paid. The sample
 * has no payment provider: an order is taken as paid a few seconds after
 * it was placed, except one in ten, which is never paid and is cancelled
 * later by `orders:cancel-stale`. The scheduler runs this every ten seconds.
 */
final readonly class SettlePayments
{
    public function __construct(private Database $db, private LoggerInterface $log, private Broadcaster $broadcast, private ClockInterface $clock)
    {
    }

    public function handle(): void
    {
        $now = $this->clock->now();
        $before = $now->modify('-' . (int) config('shop.settle_after') . ' seconds')->format('Y-m-d H:i:s');
        $settled = $this->db->transaction(function (Connection $transaction) use ($before, $now): int {
            $orders = $transaction->table('orders')->where('status', 'pending')->where('created_at', '<=', $before)->whereRaw('id % 10 <> 0')->select('id', 'version')->lockForUpdate()->get();
            $ids = array_column($orders, 'id');
            if ($ids !== []) {
                $transaction->table('orders')->whereIn('id', $ids)->increment('version', 1, ['status' => 'paid', 'updated_at' => $now->format('Y-m-d H:i:s')]);
                foreach ($orders as $order) {
                    $id = (int) $order['id'];
                    $this->broadcast->within($transaction)->to("orders.{$id}")->send(['type' => 'status', 'id' => $id, 'status' => 'paid', 'version' => (int) $order['version'] + 1]);
                }
            }
            return count($ids);
        });
        if ($settled > 0) {
            $this->log->info('{count} orders were paid.', ['count' => $settled]);
        }
    }
}
