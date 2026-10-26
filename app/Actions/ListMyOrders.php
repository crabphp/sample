<?php

declare(strict_types=1);

namespace App\Actions;

use App\User;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Page;
use Crab\Routing\FromQuery;
use Crab\Validation\Range;

/**
 * The orders of whoever asks, the newest first.
 */
final readonly class ListMyOrders
{
    public function __construct(private Database $db)
    {
    }

    public function handle(User $user, #[FromQuery, Range(min: 1)] int $page = 1): Page
    {
        return $this->db->table('orders')
            ->where('user_id', $user->id)
            ->select('id', 'status', 'total', 'created_at')
            ->orderByDesc('id')
            ->paginate(10, $page);
    }
}
