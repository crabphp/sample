<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Auth\TokenStore;
use Crab\Testing\Api;

/**
 * Exercises the Arc-rendered shop while leaving the JSON API assertions in
 * CatalogueTest and OrderTest as their own contract.
 */
final class ShopPageTest extends ShopTestCase
{
    public function testEmptyOptionalFormFiltersDoNotExcludeMatchingProducts(): void
    {
        $defaults = ['q' => '', 'category' => '', 'colour' => '', 'minPrice' => '', 'maxPrice' => ''];
        foreach ([
            ['q' => 'Mug'],
            ['category' => 'kitchen'],
            ['colour' => 'blue'],
            [],
        ] as $filters) {
            $expected = $this->api->get('/shop', $filters)->assertStatus(200)->body();
            $submitted = $this->api->get('/shop', [...$defaults, ...$filters])->assertStatus(200)->body();
            self::assertSame($expected, $submitted, 'The form must behave like the same filters without blank values.');
            self::assertStringContainsString('Stoneware Mug', $submitted);
        }
    }

    public function testCatalogueAndProductPagesRenderHtmlAndEscapeProductData(): void
    {
        $catalogue = $this->api->get('/shop')->assertStatus(200);
        self::assertStringStartsWith('text/html', $catalogue->header('Content-Type'));
        self::assertStringContainsString('Stoneware Mug', $catalogue->body());
        self::assertStringContainsString('Filter', $catalogue->body());

        $this->db->table('products')->where('id', 1)->update(['name' => '<script>alert(1)</script>']);
        $escaped = $this->api->get('/shop', ['q' => 'script'])->assertStatus(200)->body();
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $escaped);
        self::assertStringNotContainsString('<script>alert(1)</script>', $escaped);

        $product = $this->api->get('/shop/products/2')->assertStatus(200);
        self::assertStringStartsWith('text/html', $product->header('Content-Type'));
        self::assertStringContainsString('Pour-over Kettle', $product->body());
        self::assertStringContainsString('data-add-to-cart="2"', $product->body());
        $this->api->get('/shop/products/99999')->assertStatus(404);
    }

    public function testCartCheckoutPlacesAnOrderAndShowsItsConfirmation(): void
    {
        self::assertSame(2, $this->db->table('orders')->count());
        $this->api->get('/shop/cart')->assertStatus(200)->assertHeader('Content-Type', 'text/html; charset=utf-8');
        $browser = $this->browser('ada@example.com');
        $checkout = $browser->get('/shop/checkout')->assertStatus(200);
        self::assertStringNotContainsString('name="email"', $checkout->body());
        self::assertStringNotContainsString('name="password"', $checkout->body());

        $placed = $browser
            ->post('/shop/checkout', form: [
                'cart' => json_encode([['productId' => 1, 'quantity' => 2]], JSON_THROW_ON_ERROR),
            ])
            ->assertStatus(303);
        $placed->assertHeader('Location', '/shop/orders/3');
        self::assertSame('pending', $this->db->table('orders')->where('id', 3)->value('status'));
        self::assertSame(2, (int) $this->db->table('orders')->where('id', 3)->value('user_id'));
        self::assertSame(28, $this->db->table('products')->where('id', 1)->value('stock'));

        $receipt = $browser->get('/shop/orders/3')->assertStatus(200);
        self::assertStringContainsString('Order #3', $receipt->body());
        self::assertStringContainsString('Stoneware Mug', $receipt->body());
        self::assertStringContainsString('$ 28.00', $receipt->body());
        self::assertStringContainsString(
            'href="/shop/orders/3"',
            $browser->get('/shop/orders')->assertStatus(200)->body(),
        );
    }

    public function testCheckoutAndOrderHistoryRequireACookieLogin(): void
    {
        foreach (['/shop/checkout', '/shop/orders'] as $path) {
            $this->api->get($path)->assertStatus(303)->assertHeader('Location', '/shop/login');
        }
        // Only GET and HEAD redirect: an unauthenticated form cannot place an order.
        $this->api
            ->post(
                '/shop/checkout',
                headers: ['Sec-Fetch-Site' => 'same-origin'],
                form: [
                    'cart' => '[{"productId":1,"quantity":1}]',
                ],
            )
            ->assertStatus(401);
        self::assertSame(2, $this->db->table('orders')->count());
        self::assertSame(30, $this->db->table('products')->where('id', 1)->value('stock'));
    }

    public function testCheckoutRedirectIdentifiesOnlyTheSuccessfulBrowserSubmission(): void
    {
        $browser = $this->browser('ada@example.com');
        $id = '7aee85e6-3ffc-4905-a3c1-86558f4db11c';
        $browser
            ->post('/shop/checkout', form: ['cart' => '[{"productId":1,"quantity":2}]', 'checkoutId' => $id])
            ->assertStatus(303)
            ->assertHeader('Location', '/shop/orders/3#checkout=' . $id);
        $failed = $browser
            ->post('/shop/checkout', form: ['cart' => '[{"productId":99999,"quantity":1}]', 'checkoutId' => $id])
            ->assertStatus(422);
        self::assertSame('', $failed->header('Location'));
    }

    public function testOnlyTheOwnerCanReadAReceipt(): void
    {
        $this->browser('ada@example.com')->get('/shop/orders/1')->assertStatus(200);
        foreach ([$this->api, $this->browser('grace@example.com'), $this->browser('admin@example.com')] as $visitor) {
            $hidden = $visitor->get('/shop/orders/1')->assertStatus(404);
            $missing = $visitor->get('/shop/orders/99999')->assertStatus(404);
            self::assertSame($missing->body(), $hidden->body());
            self::assertStringNotContainsString('Stoneware Mug', $hidden->body());
        }
    }

    public function testMyOrdersShowsOnlyTheOwnersOrdersAcrossPages(): void
    {
        for ($id = 3; $id <= 13; ++$id) {
            $this->db
                ->table('orders')
                ->insert([
                    'id' => $id,
                    'user_id' => 2,
                    'status' => 'paid',
                    'total' => 1400,
                    'created_at' => '2026-01-02 12:00:00',
                    'updated_at' => '2026-01-02 12:00:00',
                ]);
        }
        $browser = $this->browser('ada@example.com');
        $page = $browser->get('/shop/orders')->assertStatus(200)->body();
        self::assertStringContainsString('href="/shop/orders/13"', $page);
        self::assertStringNotContainsString('href="/shop/orders/1"', $page);
        self::assertStringNotContainsString('href="/shop/orders/2"', $page);
        self::assertSame(1, preg_match('~href="(/shop/orders\?cursor=[^"]+)"~', $page, $matches));
        $older = $browser->get(html_entity_decode($matches[1]))->assertStatus(200)->body();
        self::assertStringContainsString('href="/shop/orders/1"', $older);
        self::assertStringNotContainsString('href="/shop/orders/2"', $older);
        self::assertStringNotContainsString('Older orders', $older);

        $empty = $this->browser('admin@example.com')->get('/shop/orders')->assertStatus(200)->body();
        self::assertStringContainsString('You have no orders yet.', $empty);
    }

    private function browser(string $email): Api
    {
        $token = $this->app->container->get(TokenStore::class)->issue($this->user($email), 'browser');
        return $this->api->withHeaders(['Cookie' => 'auth=' . $token, 'Sec-Fetch-Site' => 'same-origin']);
    }
}
