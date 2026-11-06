<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\CheckoutInput;
use App\Shop\Carts;
use App\Shop\Pages;
use App\User;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;
use Psr\Http\Message\ServerRequestInterface;

#[Security('cookie')]
#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
final readonly class ShopCheckout
{
    public function __construct(
        private Carts $carts,
        private Pages $pages,
        private PlaceOrder $orders,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    #[ApiResponse(303, headers: ['Location'])]
    public function show(User $user, ServerRequestInterface $request): Response
    {
        return $this->pages->render('shop.checkout', $request, $user);
    }

    #[ApiResponse(303, headers: ['Location'])]
    #[ApiResponse(409, body: ['type' => 'string'], contentType: 'text/html')]
    #[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
    public function handle(User $user, CheckoutInput $input): Response
    {
        $id = $this->carts->checkout($user, $input->checkoutId, $this->orders);
        return Pages::redirect('/orders/' . $id, 'ordered');
    }
}
