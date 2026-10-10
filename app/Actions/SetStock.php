<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Validation\Range;

/**
 * Says how many of a product there are, after a delivery or a count.
 * For administrators.
 */
final readonly class SetStock
{
    public function __construct(private Database $db, private Cache $cache)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function handle(int $id, #[Range(min: 0, max: 100000)] int $stock): array
    {
        if ($this->db->table('products')->where('id', $id)->update(['stock' => $stock, 'updated_at' => date('Y-m-d H:i:s')]) === 0
            && !$this->db->table('products')->where('id', $id)->exists()) {
            throw new HttpException(404, 'No such product.');
        }
        $this->cache->delete('categories');
        return $this->db->table('products')->find($id) ?? [];
    }
}
