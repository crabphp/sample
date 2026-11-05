<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;

/**
 * The browser cart is deliberately a JSON string in a normal form. It stays
 * in localStorage, so a request carries the complete cart and needs no session.
 */
final readonly class CheckoutInput
{
    public function __construct(
        #[Length(min: 2, max: 10_000)]
        public string $cart,
    ) {}
}
