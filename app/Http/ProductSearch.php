<?php

declare(strict_types=1);

namespace App\Http;

use App\Rules\PriceRangeInOrder;
use Crab\Validation\Length;
use Crab\Validation\Range;
use Crab\Validation\RejectUnknown;

/**
 * The catalogue's filters, read from the query string:
 * ?q=mug&category=kitchen&minPrice=1000&maxPrice=5000&page=2&perPage=12
 *
 * A name it does not know, ?sort=price, is refused rather than ignored, so
 * a client learns that the filter it meant does not exist.
 */
#[RejectUnknown, PriceRangeInOrder]
final readonly class ProductSearch
{
    public function __construct(
        #[Length(max: 60)]
        public string $q = '',
        public ?string $category = null,
        // In cents.
        #[Range(min: 0)]
        public ?int $minPrice = null,
        #[Range(min: 0)]
        public ?int $maxPrice = null,
        #[Range(min: 1)]
        public int $page = 1,
        #[Range(min: 1, max: 50)]
        public int $perPage = 12,
    ) {
    }
}
