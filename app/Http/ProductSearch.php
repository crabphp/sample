<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\Range;

/**
 * The catalogue's filters, read from the query string:
 * ?q=mug&category=kitchen&page=2&perPage=12
 */
final readonly class ProductSearch
{
    public function __construct(
        #[Length(max: 60)]
        public string $q = '',
        public ?string $category = null,
        #[Range(min: 1)]
        public int $page = 1,
        #[Range(min: 1, max: 50)]
        public int $perPage = 12,
    ) {
    }
}
