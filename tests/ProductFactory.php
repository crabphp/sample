<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Product;
use Crab\Tide\Factory;

/**
 * A plausible product for a test that needs more than the fixtures; a test says only what it is about.
 *
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    protected function definition(int $sequence): Product
    {
        $product = new Product();
        $product->sku = sprintf('TST-%03d', $sequence);
        $product->name = "Test product {$sequence}";
        $product->category = 'desk';
        $product->price = 1000;
        $product->stock = 5;
        return $product;
    }
}
