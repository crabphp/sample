<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Auth\TokenStore;
use Crab\Contracts\Storage\Storage;

final class ShopAdminTest extends ShopTestCase
{
    private function browser(string $email): Browser
    {
        $browser = new Browser($this->api);
        $browser->cookies['auth'] = $this->app->container->get(TokenStore::class)->issue(
            $this->user($email),
            'browser',
        );
        return $browser;
    }

    public function testAdministrationUsesTheExistingProductShippingAndReportActions(): void
    {
        $admin = $this->browser('admin@example.com');
        $admin->get('/admin')->assertStatus(303)->assertHeader('Location', '/admin/products');
        foreach (['/admin/products', '/admin/orders', '/admin/reports'] as $path) {
            $admin
                ->get($path)
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'text/html; charset=utf-8')
                ->assertHeader('Cache-Control', 'private, no-store');
        }
        $product = [
            'sku' => 'TEST-101',
            'name' => '<script>bad()</script>',
            'category' => 'desk',
            'price' => 1500,
            'stock' => 9,
        ];
        $admin->post('/admin/products', $product)->assertStatus(303);
        $id = $this->db->table('products')->where('sku', 'TEST-101')->value('id');
        self::assertNotNull($id);
        $html = $admin->get('/admin/products')->body();
        self::assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
        self::assertStringNotContainsString('<script>bad()', $html);
        $admin->post('/admin/products', $product)->assertStatus(409);
        $admin->post('/admin/products/' . $id . '/stock', ['stock' => 12])->assertStatus(303);
        self::assertSame(12, $this->db->table('products')->where('id', $id)->value('stock'));
        $admin->post('/admin/products/' . $id . '/stock', ['stock' => -1])->assertStatus(422);
        $admin->post('/admin/orders/1/ship')->assertStatus(303);
        $version = $this->db->table('orders')->where('id', 1)->value('version');
        $admin->post('/admin/orders/1/ship')->assertStatus(303);
        self::assertSame($version, $this->db->table('orders')->where('id', 1)->value('version'));
        self::assertSame('shipped', $this->db->table('orders')->where('id', 1)->value('status'));
        $admin
            ->post('/admin/reports', ['day' => '2026-01-01'])
            ->assertStatus(303)
            ->assertHeader('Location', '/admin/reports?day=2026-01-01');
        $report = $admin->get('/admin/reports?day=2026-01-01')->assertStatus(200)->body();
        self::assertStringContainsString('$76.00', $report);
        $stored = json_decode(
            $this->app->container->get(Storage::class)->disk('reports')->contents('2026-01-01.json'),
            true,
        );
        self::assertSame(2, $stored['orders']);
        $admin->post('/admin/reports', ['day' => '2026-02-30'])->assertStatus(422);
        $admin->get('/admin/reports?day=../../secret')->assertStatus(422);
    }

    public function testMembersAreRefusedEveryAdministrationPageAndAction(): void
    {
        $member = $this->browser('ada@example.com');
        foreach (['/admin', '/admin/products', '/admin/orders', '/admin/reports'] as $path) {
            $member->get($path)->assertStatus(403)->assertHeader('Content-Type', 'text/html; charset=utf-8');
        }
        foreach (['/admin/products', '/admin/products/1/stock', '/admin/orders/1/ship', '/admin/reports'] as $path) {
            $member->post($path)->assertStatus(403)->assertHeader('Content-Type', 'text/html; charset=utf-8');
        }
        self::assertSame(6, $this->db->table('products')->count());
        self::assertSame('paid', $this->db->table('orders')->where('id', 1)->value('status'));
    }
}
