<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Length;
use Crab\Validation\ListOf;

final readonly class PreferenceInput
{
    /** @param list<string> $categories */
    public function __construct(
        public bool $newsletter,
        #[ListOf('string'), Length(max: 20)]
        public array $categories,
    ) {}
}
