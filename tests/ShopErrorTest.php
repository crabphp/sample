<?php

declare(strict_types=1);

namespace Tests;

use App\Shop\Carts;
use Crab\Contracts\Auth\TokenStore;

final class ShopErrorTest extends ShopTestCase
{
    public function testInvalidFormInputsRenderEscapedHtmlWithRecoveryLinks(): void
    {
        $browser = new Browser($this->api);
        foreach ([
            ['/login', ['email' => 'invalid', 'password' => 'private-password']],
            ['/register', ['name' => 'X', 'email' => 'invalid', 'password' => 'short']],
            ['/cart/1/add', ['actionId' => Carts::uuid(), 'quantity' => 21]],
            ['/assistant', ['question' => '']],
        ] as [$path, $form]) {
            $response = $browser
                ->post($path, $form)
                ->assertStatus(422)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertHeader('Cache-Control', 'private, no-store');
            self::assertStringContainsString('role="alert"', $response->body());
            self::assertStringContainsString('aria-label="Shop"', $response->body());
            self::assertStringNotContainsString('private-password', $response->body());
        }
        $browser->get('/products', ['minPrice' => 100, 'maxPrice' => 1])->assertStatus(422)->assertHeader(
            'Content-Type',
            'text/html; charset=utf-8',
        );
        $this->api
            ->get('/api/products', ['minPrice' => 100, 'maxPrice' => 1])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function testMissingProductsStayRemovableAndInvalidCheckoutCannotPlaceOrders(): void
    {
        $browser = new Browser($this->api);
        $browser->cookies['auth'] = $this->app->container->get(TokenStore::class)->issue(
            $this->user('ada@example.com'),
            'browser',
        );
        $browser->add(1);
        $id = Browser::field($browser->get('/checkout')->body(), 'checkoutId');
        $this->db->table('products')->where('id', 1)->delete();
        self::assertStringContainsString('Unavailable product #1', $browser->get('/cart')->assertStatus(200)->body());
        $browser->post('/checkout', ['checkoutId' => $id])->assertStatus(422);
        $browser->post('/checkout', ['checkoutId' => 'bad'])->assertStatus(422);
        $browser->post('/cart/1/remove', ['actionId' => Carts::uuid()])->assertStatus(303);
        self::assertStringContainsString('Your cart is waiting', $browser->get('/cart')->body());
        self::assertSame(2, $this->db->table('orders')->count());
    }

    public function testServerFailuresUseTheSameDesignAndDoNotExposeDebugDetails(): void
    {
        $this->db->execute('DROP TABLE products');
        $html = $this->api
            ->get('/products')
            ->assertStatus(500)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8')
            ->body();
        self::assertStringContainsString('Something went wrong.', $html);
        self::assertStringNotContainsString('SQLSTATE', $html);
        self::assertStringNotContainsString('Stack trace', $html);
        self::assertStringContainsString('/assets/shop-v1/shop.css', $html);
    }
}
