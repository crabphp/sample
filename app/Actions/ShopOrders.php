<?php

declare(strict_types=1);

namespace App\Actions;

use App\Shop\Pages;
use App\User;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;
use Crab\Routing\FromQuery;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ShopOrders
{
    public function __construct(
        private Pages $pages,
        private ListMyOrders $orders,
    ) {}

    #[ApiResponse(
        200,
        body: ['type' => 'string'],
        description: 'Your HTML order history, newest first.',
        contentType: 'text/html',
    )]
    #[ApiResponse(303, description: 'Log in to see your orders.', headers: ['Location'])]
    #[ApiResponse(
        422,
        body: ['type' => 'string'],
        description: 'The order cursor is invalid.',
        contentType: 'text/html',
    )]
    #[Security('cookie')]
    public function handle(ServerRequestInterface $request, User $user, #[FromQuery] ?string $cursor = null): Response
    {
        return $this->pages->render('shop.orders', $request, $user, [
            'page' => $this->orders->handle($user, $cursor),
        ]);
    }
}
