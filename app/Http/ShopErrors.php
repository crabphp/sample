<?php

declare(strict_types=1);

namespace App\Http;

use App\User;
use Crab\Auth\CookieAuth;
use Crab\Contracts\Auth\Identity;
use Crab\Contracts\Auth\IdentityResolver;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Http\ErrorRenderer;
use Crab\Contracts\Http\Problem;
use Crab\Http\ErrorResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Keeps recoverable shop errors in HTML without changing the validation rules or API errors. */
final readonly class ShopErrors implements ErrorRenderer
{
    public function __construct(
        private CookieAuth $auth,
        private TokenStore $tokens,
        private IdentityResolver $users,
    ) {}

    public function render(Problem $problem, ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if ($path !== '/shop' && !str_starts_with($path, '/shop/') || !in_array($problem->status, [409, 422], true)) {
            return ErrorResponse::render($problem, $request);
        }

        // The renderer receives the original request, before CookieAuth adds its user attribute.
        $user = $request->getAttribute(Identity::ATTRIBUTE);
        if (!$user instanceof User) {
            $token = $this->auth->token($request);
            $owner = $token === null ? null : $this->tokens->owner($token);
            $user = $owner === null ? null : $this->users->resolve($owner);
        }

        return view(
            'shop.error',
            [
                'user' => $user instanceof User ? $user : null,
                'problem' => $problem,
                'errors' => [...$problem->path, ...$problem->query, ...$problem->fields],
                'path' => $path,
            ],
            $problem->status,
        )->withHeader('Cache-Control', 'private, no-store');
    }
}
