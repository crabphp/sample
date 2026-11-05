<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

/**
 * A deliberately small public receipt. It does not expose the customer's
 * address or account, so the browser can return to it without a session.
 */
final readonly class ShopOrder
{
    public function __construct(
        private Database $db,
    ) {}

    #[ApiResponse(200, description: 'The HTML order receipt.', contentType: 'text/html')]
    #[ApiResponse(404, description: 'No such order.')]
    public function handle(int $id): Response
    {
        $order = $this->db->table('orders')->find($id);
        if ($order === null) {
            throw new HttpException(404, 'No such order.');
        }
        $lines = $this->db
            ->table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', $id)
            ->select('products.name', 'order_items.quantity', 'order_items.price')
            ->get();
        return view('shop.order', ['order' => $order, 'lines' => $lines]);
    }
}
