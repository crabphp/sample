<?php

declare(strict_types=1);

namespace App;

use App\Models\Account;
use Crab\Contracts\Auth\Identity;
use Crab\Contracts\Auth\IdentityResolver;

/**
 * Finds the user a token belongs to.
 */
final readonly class Users implements IdentityResolver
{
    public function resolve(int|string $id): ?Identity
    {
        return Account::find($id)?->identity();
    }
}
