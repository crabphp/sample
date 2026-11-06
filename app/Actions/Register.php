<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\Http\Registration;
use App\User;
use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Database\Database;
use Crab\Database\DatabaseException;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Clock\ClockInterface;

/**
 * Makes an account and logs it in.
 */
final readonly class Register
{
    public function __construct(
        private Database $db,
        private PasswordHasher $passwords,
        private TokenStore $tokens,
        private ClockInterface $clock,
    ) {}

    #[ApiResponse(
        201,
        body: OpenApiSchemas::AUTH_RESULT,
        description: 'Account created and logged in.',
        headers: ['Location'],
    )]
    #[ApiResponse(409, description: 'An account with this email already exists.')]
    public function handle(Registration $registration): Response
    {
        // The hasher reads 72 bytes and no more: a longer password would be one that its first 72 bytes open.
        if (strlen($registration->password) > 72) {
            throw new HttpException(422, 'The password is too long: at most 72 bytes.');
        }

        if ($this->db->table('users')->where('email', $registration->email)->exists()) {
            throw new HttpException(409, 'There is already an account with this email.');
        }

        $now = $this->clock->now()->format('Y-m-d H:i:s');
        try {
            $id = $this->db
                ->table('users')
                ->insert([
                    'name' => $registration->name,
                    'email' => $registration->email,
                    'password' => $this->passwords->hash($registration->password),
                    'role' => 'member',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
        } catch (DatabaseException $error) {
            // The unique index also covers a request that won after the existence check.
            if (
                $error->sqlState() === '23000'
                && $this->db->table('users')->where('email', $registration->email)->exists()
            ) {
                throw new HttpException(409, 'There is already an account with this email.', previous: $error);
            }
            throw $error;
        }
        $user = new User((int) $id, $registration->name, $registration->email);

        return Response::created([
            'user' => $user,
            'token' => $this->tokens->issue($user, 'registration'),
        ], '/api/me');
    }
}
