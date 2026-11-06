<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Pattern;
use Crab\Validation\Range;

final readonly class CartChange
{
    public function __construct(
        #[Pattern('^[a-f0-9-]{36}$')]
        public string $actionId,
        #[Range(min: 1, max: 20)]
        public int $quantity = 1,
    ) {}
}
