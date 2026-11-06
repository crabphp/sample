<?php

declare(strict_types=1);

namespace Tests;

use App\Http\LoginInput;
use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Validation\Validator;
use Crab\Validation\ValidationException;

final class ShopErrorTest extends ShopTestCase
{
    public function testLoginValidationUsesTheSameErrorsInsideShopHtml(): void
    {
        $browser = $this->api->withHeaders(['Accept' => 'text/html', 'Sec-Fetch-Site' => 'same-origin']);
        foreach ([
            ['email' => 'ada@example.com', 'password' => str_repeat('secret', 13)],
            ['email' => '<invalid>', 'password' => 'private-password'],
            ['email' => 'ada@example.com'],
        ] as $form) {
            $errors = [];
            try {
                $this->app->container->get(Validator::class)->validate(LoginInput::class, $form);
            } catch (ValidationException $invalid) {
                $errors = $invalid->errors;
            }
            self::assertNotEmpty($errors);
            $html = $browser->post('/shop/login', form: $form)->assertStatus(422);
            $html->assertHeader('Content-Type', 'text/html; charset=utf-8');
            $html->assertHeader('Cache-Control', 'private, no-store');
            self::assertStringContainsString('aria-label="Shop"', $html->body());
            self::assertStringContainsString('href="/shop/login"', $html->body());
            foreach ($errors as $field => $messages) {
                self::assertStringContainsString($field, $html->body());
                foreach ($messages as $message) {
                    self::assertStringContainsString(htmlspecialchars($message, ENT_QUOTES), $html->body());
                }
            }
            self::assertStringNotContainsString($form['password'] ?? 'private-password', $html->body());
            self::assertStringNotContainsString('<invalid>', $html->body());
        }
        self::assertSame(0, $this->db->table('auth_tokens')->count());
    }

    public function testCheckoutValidationAndStockConflictsKeepNavigationAndEscapeMessages(): void
    {
        $token = $this->app->container->get(TokenStore::class)->issue($this->user('ada@example.com'), 'browser');
        $browser = $this->api->withHeaders([
            'Cookie' => 'auth=' . $token,
            'Accept' => 'text/html',
            'Sec-Fetch-Site' => 'same-origin',
        ]);
        $this->db->table('products')->where('id', 1)->update(['name' => '<img src=x onerror=alert(1)>', 'stock' => 0]);
        foreach ([
            ['[]',                                 422],
            ['[42]',                               422],
            ['[{"productId":1,"quantity":21}]',    422],
            ['[{"productId":99999,"quantity":1}]', 422],
            ['[{"productId":1,"quantity":1}]',     409],
        ] as [$cart, $status]) {
            $response = $browser->post('/shop/checkout', form: ['cart' => $cart])->assertStatus($status);
            $response->assertHeader('Content-Type', 'text/html; charset=utf-8');
            $response->assertHeader('Cache-Control', 'private, no-store');
            self::assertStringContainsString('aria-label="Shop"', $response->body());
            self::assertStringContainsString('Logged in as Ada', $response->body());
            self::assertStringContainsString('href="/shop/cart"', $response->body());
            self::assertStringContainsString('role="alert"', $response->body());
            self::assertStringNotContainsString('<img src=x', $response->body());
            if ($status === 409) {
                self::assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $response->body());
            }
        }
        self::assertSame(2, $this->db->table('orders')->count());
        $this->api
            ->actingAs($this->user('ada@example.com'))
            ->post('/orders', ['lines' => [['productId' => 1, 'quantity' => 1]]])
            ->assertStatus(409)
            ->assertHeader('Content-Type', 'application/json');
    }

    public function testCatalogueQueryErrorsAlsoStayInsideTheShop(): void
    {
        $response = $this->api
            ->get('/shop', ['minPrice' => '100', 'maxPrice' => '1'])
            ->assertStatus(422)
            ->assertHeader('Content-Type', 'text/html; charset=utf-8');
        self::assertStringContainsString('href="/shop"', $response->body());
        self::assertStringContainsString('role="alert"', $response->body());
    }
}
