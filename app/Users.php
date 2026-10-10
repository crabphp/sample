<?php

declare(strict_types=1);

namespace App;

use Crab\Contracts\Auth\Identity;
use Crab\Contracts\Auth\IdentityResolver;
use Crab\Contracts\Database\Database;

/**
 * Finds the user a token belongs to.
 */
final readonly class Users implements IdentityResolver
{
    public function __construct(private Database $db)
    {
    }

    public function resolve(int|string $id): ?Identity
    {
        $row = $this->db->table('users')->find($id);
        return $row === null ? null : self::from($row);
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function from(array $row): User
    {
        return new User((int) $row['id'], (string) $row['name'], (string) $row['email'], (string) $row['role']);
    }
}
