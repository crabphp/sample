<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\LoginInput;
use App\User;
use Crab\Auth\CookieAuth;
use Crab\Contracts\Auth\PasswordHasher;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Database\Database;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

final readonly class ShopLogin
{
    public function __construct(
        private Database $db,
        private PasswordHasher $passwords,
        private TokenStore $tokens,
        private CookieAuth $auth,
    ) {}

    #[ApiResponse(200, description: 'The HTML login form.', contentType: 'text/html')]
    public function show(?User $user): Response
    {
        return view('shop.login', ['user' => $user, 'email' => '', 'error' => null]);
    }

    #[ApiResponse(303, description: 'Logged in; continue shopping.', headers: ['Location', 'Set-Cookie'])]
    #[ApiResponse(401, description: 'These credentials do not match.', contentType: 'text/html')]
    #[ApiResponse(403, description: 'The request did not come from this application’s pages.')]
    public function handle(#[\SensitiveParameter] LoginInput $input, ?User $user): Response
    {
        $row = $this->db->table('users')->where('email', $input->email)->first();
        // Match PHP 8.4's default bcrypt cost even when the account is unknown.
        $hash = $row['password'] ?? '$2y$12$.....................................................';
        if (!$this->passwords->verify($input->password, $hash) || $row === null) {
            return view('shop.login', [
                'user' => $user,
                'email' => $input->email,
                'error' => 'These credentials do not match.',
            ])->withStatus(401);
        }

        return Response::redirect('/shop', 303)->withCookie($this->auth->cookie($this->tokens->issue(
            User::from($row),
            'browser',
        )));
    }
}
