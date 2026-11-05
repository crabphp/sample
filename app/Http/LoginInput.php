<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Email;
use Crab\Validation\Length;

final readonly class LoginInput
{
    public function __construct(
        #[Email]
        public string $email,
        #[\SensitiveParameter]
        #[Length(min: 1, max: 72)]
        public string $password,
    ) {}
}
