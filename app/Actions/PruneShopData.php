<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Psr\Clock\ClockInterface;

final readonly class PruneShopData
{
    public function __construct(
        private Database $db,
        private ClockInterface $clock,
    ) {}

    public function handle(): void
    {
        $now = $this->clock->now()->getTimestamp();
        $this->db->table('carts')->where('id', 'like', 'guest:%')->where('expires_at', '<=', $now)->delete();
        foreach (['cart_operations', 'assistant_replies'] as $table) {
            $this->db->table($table)->where('expires_at', '<=', $now)->delete();
        }
    }
}
