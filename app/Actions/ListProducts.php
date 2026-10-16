<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Page;
use Crab\Contracts\Database\Query;
use Crab\Validation\Length;
use Crab\Validation\Range;

/**
 * The catalogue, a page at a time: GET /products?q=mug&category=kitchen&page=2
 */
final readonly class ListProducts
{
    public function __construct(private Database $db)
    {
    }

    public function handle(
        #[Length(max: 60)]
        string $q = '',
        ?string $category = null,
        #[Range(min: 1)]
        int $page = 1,
        #[Range(min: 1, max: 50)]
        int $perPage = 12,
    ): Page {
        return $this->db->table('products')
            ->select('id', 'sku', 'name', 'category', 'price', 'stock')
            ->when($q !== '', static fn (Query $query): Query => $query->where('name', 'like', '%' . $q . '%'))
            ->when($category !== null, static fn (Query $query): Query => $query->where('category', $category))
            ->orderBy('name')
            ->paginate($perPage, $page);
    }
}
