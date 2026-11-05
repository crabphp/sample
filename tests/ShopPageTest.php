<?php

declare(strict_types=1);

namespace Tests;

/**
 * Exercises the Arc-rendered shop while leaving the JSON API assertions in
 * CatalogueTest and OrderTest as their own contract.
 */
final class ShopPageTest extends ShopTestCase
{
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
        $this->api->get('/shop/checkout')->assertStatus(200);

        $placed = $this->api
            ->post('/shop/checkout', form: [
                'email' => 'ada@example.com',
                'password' => 'password',
                'cart' => json_encode([['productId' => 1, 'quantity' => 2]], JSON_THROW_ON_ERROR),
            ])
            ->assertStatus(303);
        $placed->assertHeader('Location', '/shop/orders/3');
        self::assertSame('pending', $this->db->table('orders')->where('id', 3)->value('status'));
        self::assertSame(28, $this->db->table('products')->where('id', 1)->value('stock'));

        $receipt = $this->api->get('/shop/orders/3')->assertStatus(200);
        self::assertStringContainsString('Order #3', $receipt->body());
        self::assertStringContainsString('Stoneware Mug', $receipt->body());
        self::assertStringContainsString('$ 28.00', $receipt->body());
        $this->api
            ->post('/shop/checkout', form: [
                'email' => 'ada@example.com',
                'password' => 'wrong',
                'cart' => '[{"productId":1,"quantity":1}]',
            ])
            ->assertStatus(401);
    }
}
