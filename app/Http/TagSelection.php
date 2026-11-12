<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\ListOf;

/** An HTML form with no checked boxes submits no tagIds field. */
final readonly class TagSelection
{
    /** @param list<int> $tagIds */
    public function __construct(
        #[ListOf('int'), Length(max: 50)]
        public array $tagIds = [],
    ) {}
}
