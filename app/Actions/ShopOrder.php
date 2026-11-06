<?php

declare(strict_types=1);

namespace App\Actions;

use App\Shop\Pages;
use App\User;
use Crab\Contracts\Database\Database;
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
        private Database $db,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], description: 'The HTML order receipt.', contentType: 'text/html')]
    #[ApiResponse(404, description: 'No such order, or it belongs to another customer.')]
    #[ApiResponse(422, body: ['type' => 'string'], description: 'The order ID is invalid.', contentType: 'text/html')]
    #[Security('cookie')]
    public function handle(ServerRequestInterface $request, int $id, ?User $user): Response
    {
        $order = $user === null ? null : $this->db->table('orders')->where('user_id', $user->id)->find($id);
        if ($order === null) {
            throw new HttpException(404, 'No such order.');
        }
        $lines = $this->db
            ->table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', $id)
            ->select('products.name', 'order_items.quantity', 'order_items.price')
            ->get();
        return $this->pages->render('shop.order', $request, $user, [
            'user' => $user,
            'order' => $order,
            'lines' => $lines,
        ])->withHeader('Cache-Control', 'private, no-store');
    }
}
