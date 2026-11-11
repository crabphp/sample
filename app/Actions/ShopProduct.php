<?php

declare(strict_types=1);

namespace App\Actions;

use App\Shop\Pages;
use App\User;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ShopProduct
{
    public function __construct(
        private Pages $pages,
        private ShowProduct $products,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], description: 'The HTML product page.', contentType: 'text/html')]
    #[ApiResponse(404, description: 'No such product.')]
    #[ApiResponse(422, body: ['type' => 'string'], description: 'The product ID is invalid.', contentType: 'text/html')]
    public function handle(ServerRequestInterface $request, int $id, ?User $user): Response
    {
        $product = $this->products->handle($id);
        $details = [];
        if ($product->details !== null) {
            $decoded = json_decode($product->details, true);
            $details = is_array($decoded) ? $decoded : [];
        }
        return $this->pages->render('shop.product', $request, $user, [
            'product' => $product,
            'details' => $details,
        ]);
    }
}
