<?php

declare(strict_types=1);

namespace App\Models;

use Crab\Tide\Model;
use Crab\Tide\MorphedByMany;

final class Tag extends Model
{
    public int $id;
    public string $name;

    /** @var list<Product> */
    #[MorphedByMany(Product::class, 'taggable')]
    public array $products;

    /** @var list<Order> */
    #[MorphedByMany(Order::class, 'taggable')]
    public array $orders;
}
