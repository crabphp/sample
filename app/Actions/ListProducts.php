<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Http\ProductSearch;
use App\Models\Product;
use Crab\Contracts\Database\Page;
use Crab\OpenApi\Attributes\Response;
use Crab\Routing\FromQuery;

/**
 * The catalogue, a page at a time: GET /api/products?q=mug&category=kitchen&colour=blue&page=2
 */
final readonly class ListProducts
{
    #[Response(200, body: OpenApiSchemas::PRODUCT_PAGE)]
    public function handle(#[FromQuery] ProductSearch $search): Page
    {
        return Product::search($search)
            ->select('sku', 'name', 'category', 'price', 'stock')
            ->paginate($search->perPage, $search->page);
    }
}
