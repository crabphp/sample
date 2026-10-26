<?php

declare(strict_types=1);

namespace App\Rules;

use Crab\Contracts\Validation\ObjectRule;

/**
 * A price range whose ends are the right way round: a rule across two fields.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class PriceRangeInOrder implements ObjectRule
{
    public function check(object $input): array
    {
        if ($input->minPrice === null || $input->maxPrice === null || $input->minPrice <= $input->maxPrice) {
            return [];
        }

        return ['maxPrice' => 'must be at least the minimum price'];
    }
}
