<?php

declare(strict_types=1);

namespace App\Actions;

use App\Shop\Pages;
use Crab\Auth\CookieAuth;
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
    public function handle(ServerRequestInterface $request): Response
    {
        return Pages::redirect('/', 'logout')->withCookie($this->auth->logout($request));
    }
}
