<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

final class ShopCart
{
    #[ApiResponse(200, description: 'The HTML cart page.', contentType: 'text/html')]
    public function handle(): Response
    {
        return view('shop.cart');
    }
}
