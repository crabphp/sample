<?php

declare(strict_types=1);

namespace App\Http;

use App\TagOperation;
use Crab\Validation\Length;
use Crab\Validation\ListOf;

final readonly class TagInput
{
    /** @param list<int> $tagIds */
    public function __construct(
        #[ListOf('int'), Length(max: 50)]
        public array $tagIds,
        public TagOperation $operation = TagOperation::Sync,
    ) {}
}
