<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\BuildDailyReport;

/**
 * Shipping changes the status count, but keeps the payment in revenue.
 */
final class DailyReportTest extends ShopTestCase
{
    public function testShippingKeepsThePaymentInTheDailyReport(): void
    {
        $id = (int) $this->db->table('orders')->insert([
            'user_id' => $this->user('ada@example.com')->id, 'status' => 'paid', 'total' => 1000,
            'created_at' => '2026-01-31 12:00:00', 'updated_at' => '2026-01-31 12:00:00',
        ]);
        $report = $this->app->container->get(BuildDailyReport::class);
        $report->handle('2026-01-31');
        $before = json_decode(file_get_contents($this->directory . '/reports/2026-01-31.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1000, $before['revenue']);
        self::assertSame(1, $before['paid']);

        $this->api->actingAs($this->user('admin@example.com'))->put("/orders/{$id}/ship")->assertStatus(200);
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            $report->handle('2026-01-31');
        } finally {
            restore_error_handler();
        }
        $after = json_decode(file_get_contents($this->directory . '/reports/2026-01-31.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1000, $after['revenue']);
        self::assertSame([1, 0, 1, 0, 0], [$after['orders'], $after['paid'], $after['shipped'], $after['pending'], $after['cancelled']]);
        $this->api->actingAs($this->user('ada@example.com'))->get("/orders/{$id}")->assertJson('status', 'shipped');
        self::assertSame('shipped', $this->api->actingAs($this->user('ada@example.com'))->get('/orders')->json('data.0.status'));
    }
}
