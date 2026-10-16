<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\ListOf;

final readonly class OrderInput
{
    /**
     * @param list<OrderLineInput> $lines
     */
    public function __construct(
        #[ListOf(OrderLineInput::class), Length(min: 1, max: 20)] public array $lines,
    ) {
    }
}
