<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\Range;

final readonly class NewReview
{
    public function __construct(
        #[Range(min: 1, max: 5)]
        public int $rating,
        #[Length(min: 1, max: 2000)]
        public string $body,
    ) {}
}
