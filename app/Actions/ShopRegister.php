<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\Registration;
use App\Shop\BrowserCookies;
use App\Shop\Carts;
use App\Shop\Pages;
use App\User;
use Crab\Auth\CookieAuth;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(409, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(429, body: ['type' => 'string'], contentType: 'text/html')]
final readonly class ShopRegister
{
    public function __construct(
        private Register $register,
        private CookieAuth $auth,
        private Carts $carts,
        private Pages $pages,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function show(?User $user, ServerRequestInterface $request): Response
    {
        return $this->pages->render('shop.register', $request, $user);
    }

    #[ApiResponse(303, headers: ['Location', 'Set-Cookie'])]
    public function handle(Registration $input, ServerRequestInterface $request): Response
    {
        $result = json_decode((string) $this->register->handle($input)->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $user = new User($result['user']['id'], $result['user']['name'], $result['user']['email']);
        $this->carts->merge($request, $user);
        return Pages::redirect('/cart', 'registered')
            ->withCookie($this->auth->cookie($result['token']))
            ->withCookie(BrowserCookies::make('shop_cart'));
    }
}
