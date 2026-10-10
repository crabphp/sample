<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\Validation\Length;
use Crab\Validation\Pattern;
use Crab\Validation\Range;

/**
 * Adds a product to the catalogue. For administrators.
 */
final readonly class CreateProduct
{
    public function __construct(private Database $db, private Cache $cache)
    {
    }

    public function handle(
        #[Pattern('^[A-Z0-9-]{3,40}$')]
        string $sku,
        #[Length(min: 2, max: 120)]
        string $name,
        #[Length(min: 2, max: 40)]
        string $category,
        // In cents, and within what a total of many of them still holds.
        #[Range(min: 1, max: 100_000_000)]
        int $price,
        #[Range(min: 0, max: 100_000)]
        int $stock = 0,
    ): Response {
        if ($this->db->table('products')->where('sku', $sku)->exists()) {
            throw new HttpException(409, "There is already a product with the SKU {$sku}.");
        }
        $now = date('Y-m-d H:i:s');
        $id = $this->db->table('products')->insert(['sku' => $sku, 'name' => $name, 'category' => $category, 'price' => $price, 'stock' => $stock, 'created_at' => $now, 'updated_at' => $now]);
        $this->cache->delete('categories');
        return Response::created($this->db->table('products')->find($id), "/products/{$id}");
    }
}
