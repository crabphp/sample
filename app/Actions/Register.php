<?php

declare(strict_types=1);

namespace App\Actions;

use App\Users;
use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\Validation\Email;
use Crab\Validation\Length;

/**
 * Makes an account and logs it in.
 */
final readonly class Register
{
    public function __construct(private Database $db, private PasswordHasher $passwords, private TokenStore $tokens)
    {
    }

    public function handle(
        #[Email]
        string $email,
        #[Length(min: 2, max: 60)]
        string $name,
        #[Length(min: 8, max: 72)]
        string $password,
    ): Response {
        // The hasher reads 72 bytes and no more: a longer password would be one that its first 72 bytes open.
        if (strlen($password) > 72) {
            throw new HttpException(422, 'The password is too long: at most 72 bytes.');
        }
        if ($this->db->table('users')->where('email', $email)->exists()) {
            throw new HttpException(409, 'There is already an account with this email.');
        }
        $now = date('Y-m-d H:i:s');
        $id = $this->db->table('users')->insert([
            'name' => $name, 'email' => $email, 'password' => $this->passwords->hash($password), 'role' => 'member',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $user = Users::from($this->db->table('users')->find($id) ?? []);
        return Response::created(['user' => $user, 'token' => $this->tokens->issue($user, 'registration')], '/me');
    }
}
