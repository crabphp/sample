<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\LoginInput;
use App\Shop\Carts;
use App\Shop\Pages;
use App\User;
use Crab\Auth\CookieAuth;
use Crab\Contracts\Session\Session;
use Crab\Http\HttpException;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\Validation\Data;
use Psr\Http\Message\ServerRequestInterface;

#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(429, body: ['type' => 'string'], contentType: 'text/html')]
final readonly class ShopLogin
{
    public function __construct(
        private Login $login,
        private CookieAuth $auth,
        private Carts $carts,
        private Pages $pages,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function show(?User $user, ServerRequestInterface $request): Response
    {
        return $this->pages->render('shop.login', $request, $user, ['email' => '', 'error' => null]);
    }

    #[ApiResponse(303, headers: ['Location', 'Set-Cookie'])]
    #[ApiResponse(401, body: ['type' => 'string'], contentType: 'text/html')]
    public function handle(
        #[\SensitiveParameter]
        LoginInput $input,
        ?User $user,
        ServerRequestInterface $request,
        Session $session,
    ): Response {
        try {
            $result = $this->login->handle(new Data(['email' => $input->email, 'password' => $input->password]));
        } catch (HttpException $error) {
            if ($error->status !== 401) {
                throw $error;
            }
            return $this->pages->render(
                'shop.login',
                $request,
                $user,
                ['email' => $input->email, 'error' => 'These credentials do not match.'],
                401,
            );
        }
        $this->carts->merge($request, $result['user']);
        $session->forget('cart');
        // A new id once someone logs in, so one known before stops working.
        $session->regenerate();
        return Pages::redirect($session, '/cart', 'login')->withCookie($this->auth->cookie($result['token']));
    }
}
