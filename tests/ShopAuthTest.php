<?php

declare(strict_types=1);

namespace Tests;

use App\Shop\Carts;
use Crab\Contracts\Auth\TokenStore;

final class ShopAuthTest extends ShopTestCase
{
    public function testLoginAndLogoutIssueAndRevokeOnlyTheBrowserToken(): void
    {
        $browser = new Browser($this->api);
        $response = $browser
            ->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])
            ->assertStatus(303);
        self::assertStringContainsString('HttpOnly', $response->header('Set-Cookie'));
        self::assertStringContainsString('SameSite=Lax', $response->header('Set-Cookie'));
        $token = $browser->cookies['auth'];
        $tokens = $this->app->container->get(TokenStore::class);
        self::assertSame('2', (string) $tokens->owner($token));
        $other = $tokens->issue($this->user('ada@example.com'), 'other');
        self::assertStringContainsString('ada@example.com', $browser->get('/')->assertStatus(200)->body());
        $browser->post('/logout')->assertStatus(303)->assertHeader('Location', '/');
        self::assertNull($tokens->owner($token));
        self::assertSame('2', (string) $tokens->owner($other));
        $browser->cookies['auth'] = $token;
        $browser->get('/checkout')->assertStatus(303)->assertHeader('Location', '/login');
        self::assertArrayNotHasKey('auth', $browser->cookies);
    }

    public function testCredentialsAndValidationErrorsNeverReflectPasswords(): void
    {
        $browser = new Browser($this->api);
        foreach (['ada@example.com', 'missing@example.com'] as $email) {
            $response = $browser
                ->post('/login', ['email' => $email, 'password' => 'wrong-password'])
                ->assertStatus(401)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8');
            self::assertStringContainsString('These credentials do not match.', $response->body());
            self::assertStringNotContainsString('wrong-password', $response->body());
            self::assertArrayNotHasKey('auth', $browser->cookies);
        }
        $html = $browser
            ->post('/login', ['email' => '"><invalid>', 'password' => 'private-password'])
            ->assertStatus(422)
            ->body();
        self::assertStringNotContainsString('private-password', $html);
        self::assertStringNotContainsString('<invalid>', $html);
        self::assertSame(0, $this->db->table('auth_tokens')->count());
    }

    public function testRegistrationMergesTheGuestCartAndNeverAcceptsARole(): void
    {
        $browser = new Browser($this->api);
        $browser->add(1, 2)->assertStatus(303);
        $browser
            ->post('/register', [
                'name' => '<b>New customer</b>',
                'email' => 'new@example.com',
                'password' => 'long-enough-password',
                'role' => 'admin',
            ])
            ->assertStatus(303);
        self::assertSame('member', $this->db->table('users')->where('email', 'new@example.com')->value('role'));
        self::assertStringContainsString('$28.00', $browser->get('/cart')->assertStatus(200)->body());
        self::assertStringContainsString('&lt;b&gt;New customer&lt;/b&gt;', $browser->get('/')->body());
        $browser
            ->post('/register', [
                'name' => 'New customer',
                'email' => 'new@example.com',
                'password' => 'long-enough-password',
            ])
            ->assertStatus(409);
        $browser->get('/admin/products')->assertStatus(403);
    }

    public function testEveryChangingBrowserRouteRejectsCrossSiteAndMissingOrigins(): void
    {
        $forms = [
            '/login' => ['email' => 'ada@example.com', 'password' => 'password'],
            '/register' => ['email' => 'new@example.com', 'name' => 'New', 'password' => 'password'],
            '/logout' => [],
            '/cart/1/add' => ['actionId' => Carts::uuid()],
            '/cart/1/update' => ['actionId' => Carts::uuid()],
            '/cart/1/remove' => ['actionId' => Carts::uuid()],
            '/checkout' => ['checkoutId' => Carts::uuid()],
            '/assistant' => ['question' => 'mug'],
            '/assistant/stream' => ['question' => 'mug'],
            '/assistant/chat' => ['question' => 'mug'],
            '/admin/products' => [],
            '/admin/products/1/stock' => ['stock' => 99],
            '/admin/orders/1/ship' => [],
            '/admin/reports' => ['day' => '2026-01-01'],
        ];
        $token = $this->app->container->get(TokenStore::class)->issue($this->user('admin@example.com'), 'browser');
        foreach (['', 'auth=' . $token] as $cookie) {
            foreach ([
                [],
                ['Sec-Fetch-Site' => 'cross-site', 'Origin' => 'https://elsewhere.example'],
                ['Sec-Fetch-Site' => 'same-site', 'Origin' => 'https://elsewhere.example'],
            ] as $origin) {
                foreach ($forms as $path => $form) {
                    $this->api
                        ->post($path, headers: ['Cookie' => $cookie, ...$origin], form: $form)
                        ->assertStatus(403)
                        ->assertHeader('Content-Type', 'text/html; charset=utf-8');
                }
            }
        }
        self::assertSame(2, $this->db->table('orders')->count());
        self::assertSame(30, $this->db->table('products')->where('id', 1)->value('stock'));
    }

    public function testLoginAcceptsConfiguredOriginAndIgnoresExternalRedirects(): void
    {
        $this->api
            ->post(
                '/login?next=https://evil.example',
                headers: ['Origin' => $this->app->config->get('app.url')],
                form: ['email' => 'ada@example.com', 'password' => 'password', 'next' => '//evil.example'],
            )
            ->assertStatus(303)
            ->assertHeader('Location', '/cart');
    }

    public function testStaleCookieCanBeReplacedAndBrowserCookiesNeverAuthenticateTheApi(): void
    {
        $browser = new Browser($this->api);
        $browser->cookies['auth'] = str_repeat('a', 64);
        $browser->post('/login', ['email' => 'ada@example.com', 'password' => 'password'])->assertStatus(303);
        self::assertNotSame(str_repeat('a', 64), $browser->cookies['auth']);
        $browser->get('/orders')->assertStatus(200);
        $this->api
            ->withHeaders(['Cookie' => 'auth=' . $browser->cookies['auth']])
            ->get('/api/me')
            ->assertStatus(401)
            ->assertHeader('Content-Type', 'application/json');
        $this->api
            ->post('/shop/login', headers: ['Sec-Fetch-Site' => 'same-origin'], form: [])
            ->assertStatus(301)
            ->assertHeader('Location', '/login');
        $this->api->post('/shop/logout', headers: ['Sec-Fetch-Site' => 'cross-site'])->assertStatus(403);
    }
}
