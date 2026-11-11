<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Models\Product;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;

final readonly class ShowProduct
{
    #[Response(200, body: OpenApiSchemas::PRODUCT)]
    #[Response(404, description: 'No such product.')]
    public function handle(int $id): Product
    {
        return Product::find($id) ?? throw new HttpException(404, 'No such product.');
    }
}
