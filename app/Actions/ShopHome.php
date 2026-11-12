<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\ProductSearch;
use App\Models\Product;
use App\Shop\Pages;
use App\User;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ShopHome
{
    public function __construct(
        private Pages $pages,
        private ListCategories $categories,
        private ListMyOrders $orders,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function handle(ServerRequestInterface $request, ?User $user): Response
    {
        $products = Product::search(new ProductSearch())
            ->except('details', 'created_at', 'updated_at')
            ->limit(4)
            ->get();
        return $this->pages->render('shop.home', $request, $user, [
            'products' => $products,
            'categories' => $this->categories->handle(),
            'latestOrder' => $user === null ? null : $this->orders->handle($user)->items[0] ?? null,
        ]);
    }
}
