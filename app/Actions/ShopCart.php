<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\CartChange;
use App\Shop\BrowserCookies;
use App\Shop\Carts;
use App\Shop\Pages;
use App\User;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Psr\Http\Message\ServerRequestInterface;

#[ApiResponse(403, body: ['type' => 'string'], contentType: 'text/html')]
#[ApiResponse(422, body: ['type' => 'string'], contentType: 'text/html')]
final readonly class ShopCart
{
    public function __construct(
        private Carts $carts,
        private Pages $pages,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], contentType: 'text/html')]
    public function handle(ServerRequestInterface $request, ?User $user): Response
    {
        return $this->pages->render('shop.cart', $request, $user);
    }

    #[ApiResponse(303, headers: ['Location', 'Set-Cookie'])]
    public function add(int $id, CartChange $input, ServerRequestInterface $request, ?User $user): Response
    {
        return $this->change($id, $input, $request, $user, 'added');
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function update(int $id, CartChange $input, ServerRequestInterface $request, ?User $user): Response
    {
        return $this->change($id, $input, $request, $user, 'updated');
    }

    #[ApiResponse(303, headers: ['Location'])]
    public function remove(int $id, CartChange $input, ServerRequestInterface $request, ?User $user): Response
    {
        return $this->change($id, $input, $request, $user, 'removed');
    }

    private function change(
        int $id,
        CartChange $input,
        ServerRequestInterface $request,
        ?User $user,
        string $notice,
    ): Response {
        $key = $this->carts->key($request, $user) ?? 'guest:' . bin2hex(random_bytes(32));
        $this->carts->change(
            $key,
            $id,
            $notice === 'removed' ? 0 : $input->quantity,
            $notice === 'added',
            $input->actionId,
        );
        $response = Pages::redirect('/cart', $notice);
        return $user === null
            ? $response->withCookie(BrowserCookies::make('shop_cart', substr($key, 6), 30 * 86_400))
            : $response;
    }
}
