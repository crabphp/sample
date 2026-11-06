<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\OpenApiSchemas;
use App\User;
use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;
use Crab\OpenApi\Attributes\Response;
use Crab\Validation\Data;
use Crab\Validation\Email;
use Crab\Validation\Fields;

final readonly class Login
{
    public function __construct(
        private Database $db,
        private PasswordHasher $passwords,
        private TokenStore $tokens,
    ) {}

    /**
     * @return array{user: User, token: string}
     */
    #[Response(200, body: OpenApiSchemas::AUTH_RESULT, description: 'The account and a new bearer token.')]
    #[Response(401, description: 'These credentials do not match.')]
    public function handle(
        // A small body: its two fields described here, in place of a class.
        #[Fields(['email' => ['string', new Email()], 'password' => 'string'])]
        Data $credentials,
    ): array {
        $row = $this->db->table('users')->where('email', $credentials->email)->first();
        $hash = $row['password'] ?? '$2y$12$.....................................................';
        if (!$this->passwords->verify($credentials->password, $hash) || $row === null) {
            throw new HttpException(401, 'These credentials do not match.');
        }

        $user = User::from($row);

        return [
            'user' => $user,
            'token' => $this->tokens->issue($user, 'login'),
        ];
    }
}
