<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\CheckoutInput;
use App\Http\OrderInput;
use App\Http\OrderLineInput;
use App\User;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;

/**
 * Posts the browser cart through the existing PlaceOrder transaction for
 * the user authenticated by CookieAuth.
 */
#[Security('cookie')]
final readonly class ShopCheckout
{
    public function __construct(
        private PlaceOrder $orders,
    ) {}

    #[ApiResponse(200, description: 'The HTML checkout page.', contentType: 'text/html')]
    #[ApiResponse(303, description: 'Log in before checking out.', headers: ['Location'])]
    public function show(User $user): Response
    {
        return view('shop.checkout', ['user' => $user])->withHeader('Cache-Control', 'private, no-store');
    }

    #[ApiResponse(303, description: 'The order receipt.', headers: ['Location'])]
    #[ApiResponse(403, description: 'The request did not come from this application’s pages.')]
    #[ApiResponse(422, description: 'The cart is invalid.')]
    public function handle(User $user, CheckoutInput $input): Response
    {
        $decoded = json_decode($input->cart, true);
        if (!is_array($decoded) || !array_is_list($decoded) || $decoded === [] || count($decoded) > 20) {
            throw new HttpException(422, 'The cart must contain between 1 and 20 products.');
        }
        $lines = [];
        foreach ($decoded as $line) {
            if (!is_array($line)) {
                throw new HttpException(422, 'Every cart line must be an object.');
            }
            $productId = filter_var($line['productId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $quantity = filter_var($line['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => [
                'min_range' => 1,
                'max_range' => 20,
            ]]);
            if ($productId === false || $quantity === false) {
                throw new HttpException(422, 'Cart quantities must be from 1 to 20.');
            }
            $lines[] = new OrderLineInput($productId, $quantity);
        }

        $response = $this->orders->handle($user, new OrderInput($lines));
        $placed = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $receipt = '/shop/orders/' . (int) $placed['id'];
        if ($input->checkoutId !== null) {
            $receipt .= '#checkout=' . rawurlencode($input->checkoutId);
        }
        return Response::redirect($receipt, 303)->withHeader('Cache-Control', 'private, no-store');
    }
}
