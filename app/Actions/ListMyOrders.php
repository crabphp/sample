<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\OrderSummary;
use App\User;
use Crab\Contracts\Database\CursorPage;
use Crab\Contracts\Database\Database;
use Crab\OpenApi\Attributes\Response;
use Crab\Routing\FromQuery;

/**
 * The orders of whoever asks, the newest first, with how many things each
 * holds. A client scrolls through them, so they are paged by a cursor: an
 * order placed meanwhile does not push one onto the next page twice. Each
 * is read into an OrderSummary, so the page holds typed records.
 * GET /api/orders, then GET /api/orders?cursor=<next_cursor>
 */
final readonly class ListMyOrders
{
    public function __construct(
        private Database $db,
    ) {}

    #[Response(200, body: OpenApiSchemas::ORDER_CURSOR_PAGE)]
    public function handle(User $user, #[FromQuery] ?string $cursor = null): CursorPage
    {
        $items = $this->db
            ->table('order_items')
            ->selectRaw('COALESCE(SUM(order_items.quantity), 0)')
            ->whereColumn('order_items.order_id', 'orders.id');

        return $this->db
            ->table('orders')
            ->where('user_id', $user->id)
            ->select('id', 'status', 'total', 'created_at')
            ->selectSub($items, 'items')
            ->into(OrderSummary::class)
            ->orderByDesc('id')
            ->cursorPaginate(10, $cursor);
    }
}
