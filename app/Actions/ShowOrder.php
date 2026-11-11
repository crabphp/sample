<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Models\Order;
use App\User;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;

/**
 * One order with its lines. An administrator may see anyone's; everyone
 * else only their own, and is told nothing of the others. The order is
 * a model, read with its lines and each line's product.
 */
final readonly class ShowOrder
{
    /**
     * @return array<string, mixed>
     */
    #[Response(200, body: OpenApiSchemas::ORDER)]
    #[Response(404, description: 'No such order, or it belongs to another customer.')]
    public function handle(User $user, int $id): array
    {
        $order = Order::query()->with('lines.product')->find($id);
        if ($order === null || !$user->isAdmin() && (string) $order->user_id !== (string) $user->id) {
            throw new HttpException(404, 'No such order.');
        }

        $lines = [];
        foreach ($order->lines as $line) {
            if ($line->product === null) {
                continue;
            }
            $lines[] = [
                'id' => $line->product->id,
                'sku' => $line->product->sku,
                'name' => $line->product->name,
                'quantity' => $line->quantity,
                'price' => $line->price,
            ];
        }

        return [...$order->jsonSerialize(), 'lines' => $lines];
    }
}
