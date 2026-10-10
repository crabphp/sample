<?php

declare(strict_types=1);

namespace App\Actions;

use App\User;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;

/**
 * One order with its lines. An administrator may see anyone's; everyone
 * else only their own, and is told nothing of the others.
 */
final readonly class ShowOrder
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(User $user, int $id): array
    {
        $order = $this->db->table('orders')->find($id);
        if ($order === null || (!$user->isAdmin() && (string) $order['user_id'] !== (string) $user->id)) {
            throw new HttpException(404, 'No such order.');
        }
        $order['lines'] = $this->db->table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', $id)
            ->select('products.id', 'products.sku', 'products.name', 'order_items.quantity', 'order_items.price')
            ->get();
        return $order;
    }
}
