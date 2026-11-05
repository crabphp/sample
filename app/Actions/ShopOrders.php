<?php

declare(strict_types=1);

namespace App\Actions;

use App\User;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;
use Crab\Routing\FromQuery;

final readonly class ShopOrders
{
    public function __construct(
        private ListMyOrders $orders,
    ) {}

    #[ApiResponse(200, description: 'Your HTML order history, newest first.', contentType: 'text/html')]
    #[ApiResponse(303, description: 'Log in to see your orders.', headers: ['Location'])]
    #[Security('cookie')]
    public function handle(User $user, #[FromQuery] ?string $cursor = null): Response
    {
        return view('shop.orders', [
            'user' => $user,
            'page' => $this->orders->handle($user, $cursor),
        ])->withHeader('Cache-Control', 'private, no-store');
    }
}
