<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\CheckoutInput;
use App\Http\OrderInput;
use App\Http\OrderLineInput;
use App\User;
use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

/**
 * Posts the browser cart through the existing PlaceOrder transaction. The
 * sample has token auth for its API but no cookie auth, so the public shop
 * asks for the existing sample account credentials on this one request.
 */
final readonly class ShopCheckout
{
    public function __construct(
        private Database $db,
        private PasswordHasher $passwords,
        private PlaceOrder $orders,
    ) {}

    #[ApiResponse(200, description: 'The HTML checkout page.', contentType: 'text/html')]
    public function show(): Response
    {
        return view('shop.checkout');
    }

    #[ApiResponse(303, description: 'The order receipt.', headers: ['Location'])]
    #[ApiResponse(401, description: 'These credentials do not match.')]
    #[ApiResponse(422, description: 'The cart is invalid.')]
    public function handle(CheckoutInput $input): Response
    {
        $row = $this->db->table('users')->where('email', $input->email)->first();
        if ($row === null || !$this->passwords->verify($input->password, (string) $row['password'])) {
            throw new HttpException(401, 'These credentials do not match.');
        }

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

        $response = $this->orders->handle(User::from($row), new OrderInput($lines));
        $placed = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        return Response::redirect('/shop/orders/' . (int) $placed['id'], 303);
    }
}
