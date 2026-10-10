<?php

declare(strict_types=1);

namespace App\Actions;

use App\Events\OrderPlaced;
use Crab\Contracts\Cache\Cache;

/**
 * An order changes what is in stock, so what was remembered of the
 * catalogue is forgotten.
 */
final readonly class ForgetProductCache
{
    public function __construct(private Cache $cache)
    {
    }

    public function handle(OrderPlaced $event): void
    {
        $this->cache->delete('categories');
    }
}
