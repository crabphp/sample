<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Models\Product;
use Crab\Contracts\Cache\Cache;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Range;

/**
 * Says how many of a product there are, after a delivery or a count.
 * For administrators. The product is a model: read, changed and saved.
 */
final readonly class SetStock
{
    public function __construct(
        private Cache $cache,
    ) {}

    #[Response(200, body: OpenApiSchemas::PRODUCT)]
    #[Response(404, description: 'No such product.')]
    public function handle(
        int $id,
        #[Fields(['stock' => ['int', new Range(min: 0, max: 100_000)]])]
        Data $change,
    ): Product {
        $product = Product::find($id) ?? throw new HttpException(404, 'No such product.');

        // Only what changed is written: the stock, and the time Tide keeps with it.
        $product->stock = $change->stock;
        $product->save();

        $this->cache->delete('categories');

        return $product;
    }
}
