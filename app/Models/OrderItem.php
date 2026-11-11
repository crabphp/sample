<?php

declare(strict_types=1);

namespace App\Models;

use Crab\Tide\Model;

/**
 * One line of an order: a product, how many, and what each cost when ordered.
 */
final class OrderItem extends Model
{
    public int $id;
    public int $order_id;
    public int $product_id;
    public int $quantity;
    // In cents, as it was when ordered.
    public int $price;

    /**
     * A property that is a model belongs to it, by product_id. Null when the product has since been removed.
     */
    public ?Product $product;

    public function subtotal(): int
    {
        return $this->price * $this->quantity;
    }
}
