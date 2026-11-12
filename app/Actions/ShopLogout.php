<?php

declare(strict_types=1);

namespace App\Actions;

use App\Shop\Pages;
use Crab\Auth\CookieAuth;
use Crab\Contracts\Session\Session;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
final readonly class ShopLogout
{
    public function __construct(
        private CookieAuth $auth,
    ) {}

    #[ApiResponse(303, headers: ['Location', 'Set-Cookie'])]
    public function handle(ServerRequestInterface $request, Session $session): Response
    {
        // Nothing of the visit outlives the logout.
        $session->invalidate();
        return Pages::redirect($session, '/', 'logout')->withCookie($this->auth->logout($request));
    }
}
