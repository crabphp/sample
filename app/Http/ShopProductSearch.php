<?php

declare(strict_types=1);

namespace App\Http;

use App\Rules\PriceRangeInOrder;
use Crab\Validation\Length;
use Crab\Validation\OneOf;
use Crab\Validation\Pattern;
use Crab\Validation\Range;
use Crab\Validation\RejectUnknown;

/**
 * Browser filters keep dollars as text for exact conversion and form values.
 * Seven whole digits cover every product price and keep cents within an int.
 */
#[RejectUnknown, PriceRangeInOrder]
final readonly class ShopProductSearch
{
    public function __construct(
        #[Length(max: 60)]
        public string $q = '',
        public ?string $category = null,
        #[Length(max: 30)]
        public ?string $colour = null,
        #[Pattern(
            '^(?:[0-9]{1,7}(?:\.[0-9]{1,2})?)?$',
            'must be dollars from 0 to 9999999.99, with at most two decimal places',
        )]
        public ?string $minPrice = null,
        #[Pattern(
            '^(?:[0-9]{1,7}(?:\.[0-9]{1,2})?)?$',
            'must be dollars from 0 to 9999999.99, with at most two decimal places',
        )]
        public ?string $maxPrice = null,
        #[Range(min: 1)]
        public int $page = 1,
        #[Range(min: 1, max: 50)]
        public int $perPage = 12,
        #[OneOf('name', 'price-asc', 'price-desc', 'newest')]
        public string $sort = 'name',
    ) {}

    public function inCents(): ProductSearch
    {
        return new ProductSearch(
            q: $this->q,
            category: $this->category === '' ? null : $this->category,
            colour: $this->colour === '' ? null : $this->colour,
            minPrice: self::cents($this->minPrice),
            maxPrice: self::cents($this->maxPrice),
            page: $this->page,
            perPage: $this->perPage,
            sort: $this->sort,
        );
    }

    private static function cents(?string $dollars): ?int
    {
        if ($dollars === null || $dollars === '') {
            return null;
        }
        $parts = explode('.', $dollars, 2);
        return ((int) $parts[0] * 100) + (int) str_pad($parts[1] ?? '', 2, '0');
    }
}
