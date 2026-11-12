<?php

declare(strict_types=1);

namespace Tests;

use App\Shop\Carts;
use Crab\Contracts\Auth\TokenStore;

final class ShopPageTest extends ShopTestCase
{
    private function browser(?string $email = null): Browser
    {
        $browser = new Browser($this->api);
        if ($email !== null) {
            $browser->cookies['auth'] = $this->app->container->get(TokenStore::class)->issue(
                $this->user($email),
                'browser',
            );
        }
        return $browser;
    }

    public function testAllPagesRenderHtmlAndHaveAccessibleNativeForms(): void
    {
        $guest = $this->browser();
        foreach ([
            '/' => 'Built with Crab.',
            '/products' => 'Everyday, elevated.',
            '/products/1' => 'Stoneware Mug',
            '/cart' => 'Your cart is waiting',
            '/login' => 'Sign in to Crab',
            '/register' => 'Create your account',
            '/assistant' => 'Find your kind of everyday.',
        ] as $path => $text) {
            $page = $guest
                ->get($path)
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertHeader('Cache-Control', 'private, no-store');
            self::assertStringContainsString($text, $page->body());
            self::assertStringContainsString('Skip to content', $page->body());
            self::assertStringNotContainsString('localStorage', $page->body());
        }
        $member = $this->browser('ada@example.com');
        foreach (['/checkout', '/orders', '/orders/1'] as $path) {
            $html = $member
                ->get($path)
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->body();
            self::assertStringContainsString('ada@example.com', $html);
        }
        foreach (['/checkout', '/orders', '/admin/products'] as $path) {
            $guest->get($path)->assertStatus(303)->assertHeader('Location', '/login');
        }
        foreach (['/does-not-exist', '/products/9999', '/orders/9999'] as $path) {
            $guest->get($path)->assertStatus(404)->assertHeader('Content-Type', 'text/html; charset=utf-8');
        }
    }

    public function testGuestCartAddUpdateRemoveAndReplayUseOnlyForms(): void
    {
        $guest = $this->browser();
        $id = Browser::field($guest->get('/products/1')->body(), 'actionId');
        $guest
            ->post('/cart/1/add', ['actionId' => $id, 'quantity' => 2])
            ->assertStatus(303)
            ->assertHeader('Location', '/cart');
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', self::session($guest)['cart']);
        $guest->post('/cart/1/add', ['actionId' => $id, 'quantity' => 2])->assertStatus(303);
        $cart = $guest->get('/cart')->assertStatus(200);
        self::assertStringContainsString('$28.00', $cart->body());
        self::assertStringContainsString('Added to your cart.', $cart->body());
        self::assertStringStartsWith(
            'session=',
            $cart->header('Set-Cookie'),
            'The session is written again without the message.',
        );
        self::assertStringNotContainsString('Added to your cart.', $guest->get('/cart')->body());
        $guest->post('/cart/1/update', ['actionId' => Carts::uuid(), 'quantity' => 3])->assertStatus(303);
        self::assertStringContainsString('$42.00', $guest->get('/cart')->body());
        self::assertStringContainsString('Your cart is waiting', $this->browser()->get('/cart')->body());
        $guest->post('/cart/1/remove', ['actionId' => Carts::uuid()])->assertStatus(303);
        self::assertStringContainsString('Your cart is waiting', $guest->get('/cart')->body());
    }

    public function testGuestCartMergesExactlyOnceAndAccountCartSurvivesLogout(): void
    {
        $member = $this->browser('ada@example.com');
        $member->add(1, 2)->assertStatus(303);
        $guest = $this->browser();
        $guest->add(1, 3)->assertStatus(303);
        $guest->add(2)->assertStatus(303);
        $old = self::session($guest)['cart'];
        $before = $guest->cookies['session'];
        $guest
            ->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])
            ->assertStatus(303)
            ->assertHeader('Location', '/cart');
        self::assertArrayNotHasKey('cart', self::session($guest), 'The guest cart is merged and forgotten.');
        self::assertFalse(
            $this->db
                ->table('carts')
                ->where('id', 'guest:' . $old)
                ->exists(),
        );
        self::assertSame(
            ['1' => 5, '2' => 1],
            json_decode($this->db->table('carts')->where('id', 'user:2')->value('lines'), true),
        );
        // The session from before logging in, sent again: its cart was merged already, so nothing is added twice.
        $guest->cookies['session'] = $before;
        $guest->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertStatus(303);
        self::assertStringContainsString('$132.00', $guest->get('/cart')->body());
        $guest->post('/logout')->assertStatus(303);
        self::assertStringContainsString('Your cart is waiting', $guest->get('/cart')->body());
        self::assertStringContainsString('$132.00', $member->get('/cart')->body());
        self::assertStringContainsString(
            'Your cart is waiting',
            $this->browser('grace@example.com')->get('/cart')->body(),
        );
    }

    public function testCheckoutIsAtomicAndReplaysDoNotConsumeLaterAdditions(): void
    {
        $browser = $this->browser('ada@example.com');
        $browser->add(1, 2)->assertStatus(303);
        $summary = $browser->get('/checkout')->assertStatus(200);
        self::assertStringContainsString('$28.00', $summary->body());
        $id = Browser::field($summary->body(), 'checkoutId');
        $browser->post('/checkout', ['checkoutId' => $id])->assertStatus(303)->assertHeader('Location', '/orders/3');
        $browser->post('/checkout', ['checkoutId' => $id])->assertStatus(303)->assertHeader('Location', '/orders/3');
        self::assertSame(3, $this->db->table('orders')->count());
        self::assertSame(28, $this->db->table('products')->where('id', 1)->value('stock'));
        $receipt = $browser->get('/orders/3')->assertStatus(200)->body();
        self::assertStringContainsString('Order #3', $receipt);
        self::assertStringContainsString('$28.00', $receipt);
        self::assertStringContainsString('Your cart is waiting', $browser->get('/cart')->body());
        $browser->add(2)->assertStatus(303);
        $browser->post('/checkout', ['checkoutId' => $id])->assertStatus(303)->assertHeader('Location', '/orders/3');
        self::assertStringContainsString('Pour-over Kettle', $browser->get('/cart')->body());
        $browser->get('/orders/3');
        self::assertSame(1, $this->db->table('cart_checkouts')->count());
        self::assertSame(
            ['2' => 1],
            json_decode($this->db->table('carts')->where('id', 'user:2')->value('lines'), true),
        );
        $this->browser('grace@example.com')->post('/checkout', ['checkoutId' => $id])->assertStatus(409);
    }

    public function testStaleCheckoutAndStockFailureKeepTheCart(): void
    {
        $browser = $this->browser('ada@example.com');
        $browser->add(1);
        $old = Browser::field($browser->get('/checkout')->body(), 'checkoutId');
        $browser->add(2);
        $browser->post('/checkout', ['checkoutId' => $old])->assertStatus(409);
        $id = Browser::field($browser->get('/checkout')->body(), 'checkoutId');
        $this->db->table('products')->where('id', 2)->update(['stock' => 0, 'name' => '<img src=x onerror=alert(1)>']);
        $failure = $browser
            ->post('/checkout', ['checkoutId' => $id])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');
        self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $failure->body());
        self::assertSame(30, $this->db->table('products')->where('id', 1)->value('stock'));
        self::assertSame(2, $this->db->table('orders')->count());
        self::assertSame(0, $this->db->table('cart_checkouts')->count());
        self::assertCount(2, json_decode($this->db->table('carts')->where('id', 'user:2')->value('lines'), true));
    }

    public function testFiltersSortingPaginationAndEscapingUseTheSharedCatalogue(): void
    {
        $browser = $this->browser();
        $defaults = ['q' => '', 'category' => '', 'colour' => '', 'minPrice' => '', 'maxPrice' => ''];
        foreach ([['q' => 'Mug'], ['category' => 'kitchen'], ['colour' => 'blue'], []] as $filters) {
            self::assertStringContainsString(
                'Stoneware Mug',
                $browser->get('/products', [...$defaults, ...$filters])->assertStatus(200)->body(),
            );
        }
        $sort = $this->api->get('/api/products', ['sort' => 'price-asc'])->assertStatus(200)->json('data');
        self::assertSame([800, 1400, 2500, 4200, 6200, 8900], array_column($sort, 'price'));
        $page = $browser->get('/products', ['perPage' => 2])->assertStatus(200)->body();
        self::assertStringContainsString('page=2', $page);
        $this->db->table('products')->where('id', 1)->update(['name' => '<script>alert(1)</script>']);
        foreach (['/', '/products', '/products/1'] as $path) {
            $html = $browser->get($path)->assertStatus(200)->body();
            self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
            self::assertStringNotContainsString('<script>alert(1)</script>', $html);
        }
        $query = $browser->get('/products', ['q' => '"><img src=x onerror=alert(1)>'])->assertStatus(200)->body();
        self::assertStringNotContainsString('<img src=x', $query);
        self::assertStringContainsString('&quot;&gt;&lt;img', $query);
    }

    public function testReceiptsAreOwnerOnlyAndHistoryIsPaged(): void
    {
        $this->browser('ada@example.com')->get('/orders/1')->assertStatus(200);
        foreach ([
            $this->browser(),
            $this->browser('grace@example.com'),
            $this->browser('admin@example.com'),
        ] as $visitor) {
            $missing = $visitor->get('/orders/99999')->assertStatus(404)->body();
            self::assertSame($missing, $visitor->get('/orders/1')->assertStatus(404)->body());
        }
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
        $page = $browser->get('/orders')->assertStatus(200)->body();
        self::assertStringNotContainsString('href="/orders/2"', $page);
        self::assertSame(1, preg_match('~href="(/orders\?cursor=[^"]+)"~', $page, $matches));
        self::assertStringContainsString(
            'href="/orders/1"',
            $browser->get(html_entity_decode($matches[1]))->assertStatus(200)->body(),
        );
    }

    public function testLegacyRedirectsAndCachedAssets(): void
    {
        foreach ([
            '/shop' => '/',
            '/shop/' => '/',
            '/shop/cart' => '/cart',
            '/shop/products/1' => '/products/1',
            '/shop/orders' => '/orders',
            '/shop/login' => '/login',
            '/shop?category=kitchen' => '/products?category=kitchen',
        ] as $old => $new) {
            $this->api->get($old)->assertStatus(301)->assertHeader('Location', $new);
        }
        foreach ([
            'shop.css' => 'text/css; charset=utf-8',
            'shop.js' => 'text/javascript; charset=utf-8',
            'geist-sans.woff2' => 'font/woff2',
            'geist-mono.woff2' => 'font/woff2',
        ] as $file => $type) {
            $this->api
                ->get('/assets/shop-v1/' . $file)
                ->assertStatus(200)
                ->assertHeader('Content-Type', $type)
                ->assertHeader('Cache-Control', 'public, max-age=31536000, immutable');
        }
        $this->api->get('/assets/shop-v1/secrets')->assertStatus(404);
        $this->api->get('/openapi.json')->assertStatus(200)->assertHeader('Content-Type', 'application/json');
        $this->api->get('/up')->assertStatus(200);
    }

    public function testMergePreservesExcessQuantitiesUntilTheCustomerEditsThem(): void
    {
        $member = $this->browser('ada@example.com');
        $member->add(1, 20)->assertStatus(303);
        $guest = $this->browser();
        $guest->add(1, 20)->assertStatus(303);
        $guest->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertStatus(303);
        self::assertSame(
            ['1' => 40],
            json_decode($this->db->table('carts')->where('id', 'user:2')->value('lines'), true),
        );
        $id = Browser::field($guest->get('/checkout')->body(), 'checkoutId');
        $guest->post('/checkout', ['checkoutId' => $id])->assertStatus(422);
        self::assertSame(2, $this->db->table('orders')->count());
        $guest->post('/cart/1/update', ['actionId' => Carts::uuid(), 'quantity' => 2])->assertStatus(303);
        $guest
            ->post('/checkout', ['checkoutId' => Browser::field($guest->get('/checkout')->body(), 'checkoutId')])
            ->assertStatus(303);
    }

    public function testPruningExpiresGuestDataAndKeepsCustomerCartsAndCheckoutReceipts(): void
    {
        $guest = $this->browser();
        $guest->add(1)->assertStatus(303);
        $member = $this->browser('ada@example.com');
        $member->add(2)->assertStatus(303);
        $member
            ->post('/checkout', ['checkoutId' => Browser::field($member->get('/checkout')->body(), 'checkoutId')])
            ->assertStatus(303);
        $member->add(1)->assertStatus(303);
        $this->clock->advance('31 days');
        self::assertStringContainsString('Your cart is waiting', $guest->get('/cart')->body());
        $this->app->container->get(\App\Actions\PruneShopData::class)->handle();
        self::assertSame(1, $this->db->table('carts')->count());
        self::assertSame(1, $this->db->table('cart_checkouts')->count());
        self::assertSame(0, $this->db->table('cart_operations')->count());
        self::assertSame(
            ['1' => 1],
            json_decode($this->db->table('carts')->where('id', 'user:2')->value('lines'), true),
        );
    }

    /**
     * What the browser's session cookie holds. The cookie driver signs it but does not hide it.
     *
     * @return array<string, mixed>
     */
    private static function session(Browser $browser): array
    {
        $payload = explode('.', $browser->cookies['session'] ?? '')[0];
        $record = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);
        return is_array($record) && is_array($record['values'] ?? null) ? $record['values'] : [];
    }
}
