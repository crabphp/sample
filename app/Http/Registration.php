<?php

declare(strict_types=1);

namespace App\Http;

use Crab\Validation\Email;
use Crab\Validation\Length;

final readonly class Registration
{
    public function __construct(
        #[Email]
        public string $email,
        #[Length(min: 2, max: 60)]
        public string $name,
        #[Length(min: 8, max: 72)]
        public string $password,
    ) {
    }
}
