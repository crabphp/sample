<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Email;
use Crab\Validation\Length;
use Crab\Validation\Pattern;

final readonly class Registration
{
    public function __construct(
        #[Email]
        public string $email,
        #[Length(min: 2, max: 60)]
        public string $name,
        #[\SensitiveParameter]
        #[Length(min: 8, max: 72)]
        #[Pattern('^[^\x00]*$', 'must not contain a null byte')]
        public string $password,
    ) {}
}
