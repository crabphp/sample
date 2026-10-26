<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\NewProduct;
use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Psr\Clock\ClockInterface;

/**
 * Adds a product to the catalogue. For administrators.
 */
final readonly class CreateProduct
{
    public function __construct(
        private Database $db,
        private Cache $cache,
        private ClockInterface $clock,
    ) {
    }

    public function handle(NewProduct $product): Response
    {
        if ($this->db->table('products')->where('sku', $product->sku)->exists()) {
            throw new HttpException(409, "There is already a product with the SKU {$product->sku}.");
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $id = $this->db->table('products')->insert([
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'price' => $product->price,
            'stock' => $product->stock,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->cache->delete('categories');

        return Response::created($this->db->table('products')->find($id), "/products/{$id}");
    }
}
