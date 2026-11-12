<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Models\Account;
use App\Models\Product;
use App\User;
use Crab\Contracts\Database\Page;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\Routing\FromQuery;
use Crab\Validation\Range;

final readonly class SavedProducts
{
    #[ApiResponse(200, body: OpenApiSchemas::SAVED_PRODUCT_PAGE)]
    public function handle(User $user, #[FromQuery]
        #[Range(min: 1)] int $page = 1): Page
    {
        return Account::find($user->id)
            ->related('savedProducts')
            ->select('sku', 'name', 'category', 'price', 'stock')
            ->orderBy('id', 'desc')
            ->simplePaginate(20, $page);
    }

    #[ApiResponse(204)]
    #[ApiResponse(404, description: 'No such product.')]
    public function save(User $user, int $id): Response
    {
        $product = Product::find($id) ?? throw new HttpException(404, 'No such product.');
        Account::find($user->id)->attach('savedProducts', [$product->id]);
        return new Response(204);
    }

    #[ApiResponse(204)]
    public function remove(User $user, int $id): Response
    {
        Account::find($user->id)->detach('savedProducts', [$id]);
        return new Response(204);
    }
}
