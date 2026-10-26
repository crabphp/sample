<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\Pattern;
use Crab\Validation\Range;

final readonly class NewProduct
{
    public function __construct(
        #[Pattern('^[A-Z0-9-]{3,40}$')]
        public string $sku,
        #[Length(min: 2, max: 120)]
        public string $name,
        #[Length(min: 2, max: 40)]
        public string $category,
        // In cents, and within what a total of many of them still holds.
        #[Range(min: 1, max: 100_000_000)]
        public int $price,
        #[Range(min: 0, max: 100_000)]
        public int $stock = 0,
    ) {
    }
}
