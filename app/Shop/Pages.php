<?php

declare(strict_types=1);

namespace App\Shop;

use App\User;
use Crab\Contracts\Session\Session;
use Crab\Http\Response;
use Psr\Http\Message\ServerRequestInterface;

final readonly class Pages
{
    private const FLASH = [
        'added' => 'Added to your cart.',
        'updated' => 'Your cart has been updated.',
        'removed' => 'Item removed from your cart.',
        'login' => 'Welcome back. Your cart is ready.',
        'registered' => 'Your account is ready. Welcome to Crab.',
        'logout' => 'You have been signed out.',
        'ordered' => 'Your order has been placed.',
        'product' => 'Product created.',
        'tags' => 'Tags saved.',
        'stock' => 'Stock updated.',
        'shipped' => 'Order marked as shipped.',
        'report' => 'Daily report generated.',
    ];

    public function __construct(
        private Carts $carts,
    ) {}

    public function render(
        string $view,
        ServerRequestInterface $request,
        ?User $user,
        array $data = [],
        int $status = 200,
    ): Response {
        $cart = $this->carts->snapshot($this->carts->key($request, $user));
        $session = $request->getAttribute(Session::ATTRIBUTE);
        $flash = $session instanceof Session ? $session->get('status') : null;
        $response = view(
            $view,
            [...$data, 'user' => $user, 'cart' => $cart, 'flash' => is_string($flash) ? $flash : null],
            $status,
        )
            ->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');
        return $response;
    }

    /**
     * Back to a page, with a message for it to show once.
     */
    public static function redirect(Session $session, string $path, string $notice): Response
    {
        $session->flash('status', self::FLASH[$notice]);
        return Response::redirect($path, 303)->withHeader('Cache-Control', 'private, no-store');
    }
}
