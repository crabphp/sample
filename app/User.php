<?php

declare(strict_types=1);

namespace App;

use Crab\Contracts\Auth\Identity;

/**
 * Whoever makes a request, once they have logged in.
 */
final readonly class User implements Identity, \JsonSerializable
{
    public function __construct(
        public int|string $id,
        public string $name,
        public string $email,
        public string $role = 'member',
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function from(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['name'],
            (string) $row['email'],
            (string) $row['role'],
        );
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * @return array{id: int|string, name: string, email: string, role: string}
     */
    public function jsonSerialize(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
