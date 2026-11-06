<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Pattern;

final readonly class CheckoutInput
{
    public function __construct(
        #[Pattern('^[a-f0-9-]{36}$')]
        public string $checkoutId,
    ) {}
}
