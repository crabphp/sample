<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\ProductSearch;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Page;
use Crab\Contracts\Database\Query;
use Crab\Routing\FromQuery;

/**
 * The catalogue, a page at a time: GET /products?q=mug&category=kitchen&page=2
 */
final readonly class ListProducts
{
    public function __construct(private Database $db)
    {
    }

    public function handle(#[FromQuery] ProductSearch $search): Page
    {
        return $this->db->table('products')
            ->select('id', 'sku', 'name', 'category', 'price', 'stock')
            ->when($search->q !== '', static fn (Query $query): Query => $query->where('name', 'like', '%' . $search->q . '%'))
            ->when($search->category !== null, static fn (Query $query): Query => $query->where('category', $search->category))
            ->when($search->minPrice !== null, static fn (Query $query): Query => $query->where('price', '>=', $search->minPrice))
            ->when($search->maxPrice !== null, static fn (Query $query): Query => $query->where('price', '<=', $search->maxPrice))
            ->orderBy('name')
            ->paginate($search->perPage, $search->page);
    }
}
