<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\Pattern;

/**
 * The browser cart is deliberately a JSON string in a normal form. It stays
 * in localStorage, so a request carries the complete cart and needs no session.
 */
final readonly class CheckoutInput
{
    public function __construct(
        #[Length(min: 2, max: 10_000)]
        public string $cart,
        // Correlates the successful redirect with this tab's submitted cart.
        #[Pattern('^[a-f0-9-]{36}$')]
        public ?string $checkoutId = null,
    ) {}
}
