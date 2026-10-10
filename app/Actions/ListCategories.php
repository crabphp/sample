<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;

/**
 * Each category with how many products it has and how many are in
 * stock. Asked for on every page of a shop and slow to count, so it is
 * remembered for a minute, and forgotten when an order changes the stock.
 */
final readonly class ListCategories
{
    public function __construct(private Database $db, private Cache $cache)
    {
    }

    /**
     * @return list<array{category: string, products: int, in_stock: int}>
     */
    public function handle(): array
    {
        return $this->cache->remember('categories', 60, fn (): array => array_map(
            static fn (array $row): array => ['category' => (string) $row['category'], 'products' => (int) $row['products'], 'in_stock' => (int) $row['in_stock']],
            $this->db->table('products')
                ->select('category')
                ->selectRaw('COUNT(*) AS products')
                ->selectRaw('SUM(CASE WHEN stock > 0 THEN 1 ELSE 0 END) AS in_stock')
                ->groupBy('category')
                ->orderBy('category')
                ->get(),
        ));
    }
}
