<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Validation\Data;
use Crab\Validation\Fields;
use Crab\Validation\Range;
use Psr\Clock\ClockInterface;

/**
 * Says how many of a product there are, after a delivery or a count.
 * For administrators.
 */
final readonly class SetStock
{
    public function __construct(
        private Database $db,
        private Cache $cache,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(int $id, #[Fields(['stock' => ['int', new Range(min: 0, max: 100_000)]])] Data $change): array
    {
        $updated = $this->db->table('products')->where('id', $id)->update([
            'stock' => $change->stock,
            'updated_at' => $this->clock->now()->format('Y-m-d H:i:s'),
        ]);

        // An existing product can report zero changed rows when its stock is unchanged.
        if ($updated === 0 && !$this->db->table('products')->where('id', $id)->exists()) {
            throw new HttpException(404, 'No such product.');
        }

        $this->cache->delete('categories');

        return $this->db->table('products')->find($id) ?? [];
    }
}
