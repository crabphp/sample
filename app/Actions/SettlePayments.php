<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Psr\Log\LoggerInterface;

/**
 * Marks the orders whose payment has come through as paid. The sample
 * has no payment provider: an order is taken as paid a few seconds after
 * it was placed, except one in ten, which is never paid and is cancelled
 * later by `orders:cancel-stale`. The scheduler runs this every ten seconds.
 */
final readonly class SettlePayments
{
    public function __construct(private Database $db, private LoggerInterface $log)
    {
    }

    public function handle(): void
    {
        $before = date('Y-m-d H:i:s', time() - (int) config('shop.settle_after'));
        $settled = $this->db->table('orders')->where('status', 'pending')->where('created_at', '<=', $before)->whereRaw('id % 10 <> 0')->update(['status' => 'paid', 'updated_at' => date('Y-m-d H:i:s')]);
        if ($settled > 0) {
            $this->log->info('{count} orders were paid.', ['count' => $settled]);
        }
    }
}
