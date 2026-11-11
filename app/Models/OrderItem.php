<?php

declare(strict_types=1);

namespace App\Models;

use Crab\Tide\BelongsTo;
use Crab\Tide\Model;
use Crab\Tide\Table;

/**
 * One line of an order: a product, how many, and what each cost when ordered.
 */
#[Table('order_items')]
final class OrderItem extends Model
{
    public int $id;
    public int $order_id;
    public int $product_id;
    public int $quantity;
    // In cents, as it was when ordered.
    public int $price;

    /**
     * Null when the product has since been removed.
     */
    #[BelongsTo('product_id')]
    public ?Product $product;
}
