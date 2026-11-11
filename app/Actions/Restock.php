<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;
use Crab\Contracts\Cache\Cache;
use Psr\Log\LoggerInterface;

/**
 * A delivery: whatever is running low is filled up again. The scheduler
 * runs it every minute, so that the pretend customers never empty the shop.
 */
final readonly class Restock
{
    public function __construct(
        private Cache $cache,
        private LoggerInterface $log,
    ) {}

    public function handle(): void
    {
        // A bulk change on the model: Tide writes updated_at with it, in the application's zone.
        $filled = Product::query()->where('stock', '<', 10)->increment('stock', 60);

        if ($filled > 0) {
            $this->cache->delete('categories');
            $this->log->info('A delivery restocked {count} products.', ['count' => $filled]);
        }
    }
}
