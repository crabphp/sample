<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\NewProduct;
use App\Http\OpenApiSchemas;
use App\Models\Product;
use Crab\Contracts\Cache\Cache;
use Crab\Database\DatabaseException;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

/**
 * Adds a product to the catalogue. For administrators.
 */
final readonly class CreateProduct
{
    public function __construct(
        private Cache $cache,
    ) {}

    #[ApiResponse(201, body: OpenApiSchemas::PRODUCT, description: 'Product created.', headers: ['Location'])]
    #[ApiResponse(409, description: 'A product with this SKU already exists.')]
    public function handle(NewProduct $product): Response
    {
        if (Product::query()->where('sku', $product->sku)->exists()) {
            throw new HttpException(409, "There is already a product with the SKU {$product->sku}.");
        }

        $made = new Product();
        $made->sku = $product->sku;
        $made->name = $product->name;
        $made->category = $product->category;
        $made->price = $product->price;
        $made->stock = $product->stock;
        try {
            // Its times are Tide's to write.
            $made->save();
        } catch (DatabaseException $error) {
            // The unique index also covers a request that won after the existence check.
            if ($error->sqlState() === '23000' && Product::query()->where('sku', $product->sku)->exists()) {
                throw new HttpException(409, 'There is already a product with this SKU.', previous: $error);
            }
            throw $error;
        }

        $this->cache->delete('categories');

        return Response::created($made, "/api/products/{$made->id}");
    }
}
