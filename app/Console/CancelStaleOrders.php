<?php

declare(strict_types=1);

namespace App\Console;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;

final readonly class CancelStaleOrders implements Command
{
    public function __construct(private Database $db, private Cache $cache)
    {
    }

    public function description(): string
    {
        return 'Cancel the orders that were never paid, and put what they took back in stock';
    }

    public function handle(Input $input, Output $output): int
    {
        $before = date('Y-m-d H:i:s', time() - 60 * (int) config('shop.cancel_after'));
        $cancelled = $this->db->transaction(static function (Connection $db) use ($before): int {
            $ids = $db->table('orders')->where('status', 'pending')->where('created_at', '<=', $before)->lockForUpdate()->pluck('id');
            foreach ($ids as $id) {
                foreach ($db->table('order_items')->where('order_id', $id)->get() as $line) {
                    $db->table('products')->where('id', $line['product_id'])->increment('stock', (int) $line['quantity']);
                }
            }
            if ($ids !== []) {
                $db->table('orders')->whereIn('id', $ids)->update(['status' => 'cancelled', 'updated_at' => date('Y-m-d H:i:s')]);
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
