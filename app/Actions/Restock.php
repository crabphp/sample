<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * A delivery: whatever is running low is filled up again. The scheduler
 * runs it every minute, so that the pretend customers never empty the shop.
 */
final readonly class Restock
{
    public function __construct(
        private Database $db,
        private Cache $cache,
        private LoggerInterface $log,
        private ClockInterface $clock,
    ) {
    }

    public function handle(): void
    {
        $filled = $this->db->table('products')
            ->where('stock', '<', 10)
            ->increment('stock', 60, ['updated_at' => $this->clock->now()->format('Y-m-d H:i:s')]);

        if ($filled > 0) {
            $this->cache->delete('categories');
            $this->log->info('A delivery restocked {count} products.', ['count' => $filled]);
        }
    }
}
