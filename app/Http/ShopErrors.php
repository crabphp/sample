<?php

declare(strict_types=1);

namespace App\Http;

use App\Shop\Pages;
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
        private Pages $pages,
        private CookieAuth $auth,
        private TokenStore $tokens,
        private IdentityResolver $users,
    ) {}

    public function render(Problem $problem, ServerRequestInterface $request): ResponseInterface
    {
        $path = $request->getUri()->getPath();
        if (
            $path === '/api'
            || str_starts_with($path, '/api/')
            || str_starts_with($path, '/clockwerk')
            || $path === '/up'
        ) {
            return ErrorResponse::render($problem, $request);
        }

        if ($problem->status >= 500) {
            // Error rendering must still work when the database is unavailable.
            return view(
                'shop.error',
                ['user' => null, 'cart' => [], 'flash' => null, 'problem' => $problem, 'errors' => [], 'path' => $path],
                $problem->status,
            )->withHeader('Cache-Control', 'private, no-store');
        }

        // The renderer receives the original request, before CookieAuth adds its user attribute.
        $user = $request->getAttribute(Identity::ATTRIBUTE);
        if (!$user instanceof User) {
            $token = $this->auth->token($request);
            $owner = $token === null ? null : $this->tokens->owner($token);
            $user = $owner === null ? null : $this->users->resolve($owner);
        }

        return $this->pages->render(
            'shop.error',
            $request,
            $user instanceof User ? $user : null,
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
