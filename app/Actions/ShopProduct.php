<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

final readonly class ShopProduct
{
    public function __construct(
        private ShowProduct $products,
    ) {}

    #[ApiResponse(200, description: 'The HTML product page.', contentType: 'text/html')]
    #[ApiResponse(404, description: 'No such product.')]
    public function handle(int $id): Response
    {
        $product = $this->products->handle($id);
        $details = [];
        if (is_string($product['details'] ?? null)) {
            $decoded = json_decode($product['details'], true);
            $details = is_array($decoded) ? $decoded : [];
        }
        return view('shop.product', ['product' => $product, 'details' => $details]);
    }
}
