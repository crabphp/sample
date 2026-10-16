<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Range;

final readonly class OrderLineInput
{
    public function __construct(
        public int $productId,

        #[Range(min: 1, max: 20)]
        public ?int $quantity = 1,
    ) {
    }
}
