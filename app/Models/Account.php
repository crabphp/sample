<?php

declare(strict_types=1);

namespace App\Models;

use App\User;
use Crab\Tide\Hidden;
use Crab\Tide\Model;
use Crab\Tide\Table;

/**
 * Someone's account, as it is stored. App\User is the same person as the
 * one making a request; this is the row their account is kept in.
 */
#[Table('users')]
final class Account extends Model
{
    public int $id;
    public string $name;
    public string $email;
    // Never written out, though the code that checks a login reads it.
    #[Hidden]
    public string $password;
    public string $role = 'member';
    public ?\DateTimeImmutable $created_at;
    public ?\DateTimeImmutable $updated_at;

    public function identity(): User
    {
        return new User($this->id, $this->name, $this->email, $this->role);
    }
}
