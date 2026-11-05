<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Auth\TokenStore;

final class ShopAuthTest extends ShopTestCase
{
    public function testLoginSetsACookieAndTheLayoutShowsItsUser(): void
    {
        $page = $this->api->get('/shop/login')->assertStatus(200);
        self::assertStringContainsString('action="/shop/login"', $page->body());
        self::assertStringContainsString('autocomplete="current-password"', $page->body());

        $response = $this->api
            ->post(
                '/shop/login',
                headers: ['Sec-Fetch-Site' => 'same-origin'],
                form: [
                    'email' => 'ada@example.com',
                    'password' => 'password',
                ],
            )
            ->assertStatus(303)
            ->assertHeader('Location', '/shop');
        $cookie = $response->header('Set-Cookie');
        self::assertMatchesRegularExpression('/^auth=[a-f0-9]{64};/', $cookie);
        self::assertStringContainsString('Path=/', $cookie);
        self::assertStringContainsString('HttpOnly', $cookie);
        self::assertStringContainsString('SameSite=Lax', $cookie);
        $pair = explode(';', $cookie, 2)[0];
        $token = substr($pair, strlen('auth='));
        self::assertSame('2', (string) $this->app->container->get(TokenStore::class)->owner($token));

        $browser = $this->api->withHeaders(['Cookie' => $pair]);
        foreach ([
            '/shop',
            '/shop/products/1',
            '/shop/cart',
            '/shop/login',
            '/shop/checkout',
            '/shop/orders',
            '/shop/orders/1',
        ] as $path) {
            $body = $browser->get($path)->assertStatus(200)->body();
            self::assertStringContainsString('Logged in as Ada (ada@example.com)', $body);
            self::assertStringContainsString('action="/shop/logout"', $body);
        }
        self::assertStringNotContainsString('Logged in as', $this->api->get('/shop')->body());
    }

    public function testRejectedCredentialsShowTheFormWithoutIssuingAToken(): void
    {
        foreach (['ada@example.com', 'missing@example.com'] as $email) {
            $response = $this->api
                ->post(
                    '/shop/login',
                    headers: ['Sec-Fetch-Site' => 'same-origin'],
                    form: [
                        'email' => $email,
                        'password' => 'wrong-password',
                    ],
                )
                ->assertStatus(401);
            self::assertStringStartsWith('text/html', $response->header('Content-Type'));
            self::assertStringContainsString('These credentials do not match.', $response->body());
            self::assertStringNotContainsString('wrong-password', $response->body());
            self::assertSame('', $response->header('Set-Cookie'));
        }
        self::assertSame(0, $this->db->table('auth_tokens')->count());
    }

    public function testLogoutRevokesOnlyItsTokenAndClearsTheCookie(): void
    {
        $tokens = $this->app->container->get(TokenStore::class);
        $token = $tokens->issue($this->user('ada@example.com'), 'browser');
        $other = $tokens->issue($this->user('ada@example.com'), 'other browser');
        $browser = $this->api->withHeaders(['Cookie' => 'auth=' . $token, 'Sec-Fetch-Site' => 'same-origin']);

        $response = $browser->post('/shop/logout')->assertStatus(303)->assertHeader('Location', '/shop');
        self::assertStringStartsWith('auth=;', $response->header('Set-Cookie'));
        self::assertStringContainsString('Max-Age=0', $response->header('Set-Cookie'));
        self::assertNull($tokens->owner($token));
        self::assertSame('2', (string) $tokens->owner($other));
        $browser->get('/shop/checkout')->assertStatus(303)->assertHeader('Location', '/shop/login');
        $public = $browser->get('/shop')->assertStatus(200);
        self::assertStringNotContainsString('Logged in as', $public->body());
        self::assertStringContainsString('Max-Age=0', $public->header('Set-Cookie'));
        $browser->get('/shop/orders/1')->assertStatus(404);
        $this->api->post('/shop/logout', headers: ['Sec-Fetch-Site' => 'same-origin'])->assertStatus(303);
    }

    public function testEveryChangingShopRouteRefusesCrossSiteAndMissingOriginHeaders(): void
    {
        $tokens = $this->app->container->get(TokenStore::class);
        $token = $tokens->issue($this->user('ada@example.com'), 'browser');
        $forms = [
            '/shop/login' => ['email' => 'ada@example.com', 'password' => 'password'],
            '/shop/logout' => [],
            '/shop/checkout' => ['cart' => '[{"productId":1,"quantity":2}]'],
        ];
        foreach ([[], ['Cookie' => 'auth=' . $token]] as $cookies) {
            foreach ([
                ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://elsewhere.example'],
                ['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://elsewhere.example'],
                [],
            ] as $headers) {
                foreach ($forms as $path => $form) {
                    $this->api->post($path, headers: [...$cookies, ...$headers], form: $form)->assertStatus(403);
                }
            }
        }
        self::assertSame(1, $this->db->table('auth_tokens')->count());
        self::assertSame('2', (string) $tokens->owner($token));
        self::assertSame(2, $this->db->table('orders')->count());
        self::assertSame(30, $this->db->table('products')->where('id', 1)->value('stock'));
    }

    public function testLoginAcceptsTheConfiguredOriginWithoutFetchMetadata(): void
    {
        $this->api
            ->post(
                '/shop/login',
                headers: ['Origin' => $this->app->config->get('app.url')],
                form: [
                    'email' => 'ada@example.com',
                    'password' => 'password',
                ],
            )
            ->assertStatus(303);
    }
}
