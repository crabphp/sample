<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Order;
use App\Shop\Pages;
use App\User;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\OpenApi\Attributes\Security;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Only the owner may read a receipt, including when another visitor is an
 * administrator. Anonymous visitors get the same 404 as other non-owners.
 */
final readonly class ShopOrder
{
    public function __construct(
        private Pages $pages,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], description: 'The HTML order receipt.', contentType: 'text/html')]
    #[ApiResponse(404, description: 'No such order, or it belongs to another customer.')]
    #[ApiResponse(422, body: ['type' => 'string'], description: 'The order ID is invalid.', contentType: 'text/html')]
    #[Security('cookie')]
    public function handle(ServerRequestInterface $request, int $id, ?User $user): Response
    {
        $order = $user === null ? null : Order::query()->where('user_id', $user->id)->with('lines.product')->find($id);
        if ($order === null) {
            throw new HttpException(404, 'No such order.');
        }

        // A line whose product has since been removed is not shown, as before.
        $lines = array_values(array_filter($order->lines, static fn($line): bool => $line->product !== null));

        return $this->pages->render('shop.order', $request, $user, [
            'order' => $order,
            'lines' => $lines,
        ]);
    }
}
