<?php

declare(strict_types=1);

namespace App\Console;

use Crab\Contracts\Broadcast\Broadcaster;
use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Psr\Clock\ClockInterface;

final readonly class CancelStaleOrders implements Command
{
    public function __construct(
        private Database $db,
        private Cache $cache,
        private Broadcaster $broadcast,
        private ClockInterface $clock,
    ) {
    }

    public function description(): string
    {
        return 'Cancel the orders that were never paid, and put what they took back in stock';
    }

    public function handle(Input $input, Output $output): int
    {
        $now = $this->clock->now();
        $before = $now->modify('-' . (int) config('shop.cancel_after') . ' minutes')->format('Y-m-d H:i:s');

        $cancelled = $this->db->transaction(function (Connection $db) use ($before, $now): int {
            $orders = $db->table('orders')
                ->where('status', 'pending')
                ->where('created_at', '<=', $before)
                ->select('id', 'version')
                ->lockForUpdate()
                ->get();

            if ($orders === []) {
                return 0;
            }

            $ids = array_column($orders, 'id');
            $stock = $db->table('order_items')
                ->whereIn('order_id', $ids)
                ->select('product_id')
                ->selectRaw('SUM(quantity) AS quantity')
                ->groupBy('product_id')
                ->orderBy('product_id')
                ->get();

            // Restore each product once, in the same lock order used when placing orders.
            foreach ($stock as $line) {
                $db->table('products')
                    ->where('id', $line['product_id'])
                    ->increment('stock', (int) $line['quantity']);
            }

            $db->table('orders')->whereIn('id', $ids)->increment('version', 1, [
                'status' => 'cancelled',
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            foreach ($orders as $order) {
                $id = (int) $order['id'];
                $this->broadcast->within($db)->to("orders.{$id}")->send([
                    'type' => 'status',
                    'id' => $id,
                    'status' => 'cancelled',
                    'version' => (int) $order['version'] + 1,
                ]);
            }

            return count($ids);
        });

        if ($cancelled > 0) {
            $this->cache->delete('categories');
        }

        $output->line($cancelled === 1 ? 'Cancelled 1 order.' : "Cancelled {$cancelled} orders.");
        return 0;
    }
}
