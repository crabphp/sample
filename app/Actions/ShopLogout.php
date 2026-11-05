<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Auth\CookieAuth;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

final readonly class ShopLogout
{
    public function __construct(
        private CookieAuth $auth,
    ) {}

    #[ApiResponse(303, description: 'Logged out; return to the catalogue.', headers: ['Location', 'Set-Cookie'])]
    #[ApiResponse(403, description: 'The request did not come from this application’s pages.')]
    public function handle(ServerRequestInterface $request): Response
    {
        return Response::redirect('/shop', 303)->withCookie($this->auth->logout($request));
    }
}
