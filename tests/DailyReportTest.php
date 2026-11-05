<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\BuildDailyReport;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Shipping changes the status count, but keeps the payment in revenue.
 */
final class DailyReportTest extends ShopTestCase
{
    #[DataProvider('invalidDays')]
    public function testAnInvalidDayCannotOverwriteAnotherFile(string $day): void
    {
        $outside = $this->directory . '/outside.json';
        file_put_contents($outside, 'Keep this file.');
        $error = null;
        try {
            $this->app->container->get(BuildDailyReport::class)->handle($day);
        } catch (\InvalidArgumentException $caught) {
            $error = $caught;
        }
        self::assertSame('Keep this file.', file_get_contents($outside));
        self::assertInstanceOf(\InvalidArgumentException::class, $error);
        self::assertDirectoryDoesNotExist($this->directory . '/reports');
    }

    public static function invalidDays(): iterable
    {
        yield 'parent directory' => ['../outside'];
        yield 'impossible date' => ['2026-02-30'];
        yield 'relative date' => ['yesterday'];
    }

    public function testAReportCannotFollowASymlinkOverAnotherFile(): void
    {
        mkdir($this->directory . '/reports');
        $outside = $this->directory . '/outside.json';
        file_put_contents($outside, 'Keep this file.');
        symlink($outside, $this->directory . '/reports/2026-01-31.json');
        $error = null;
        try {
            $this->app->container->get(BuildDailyReport::class)->handle('2026-01-31');
        } catch (\Crab\Contracts\Storage\StorageException $caught) {
            $error = $caught;
        }
        self::assertSame('Keep this file.', file_get_contents($outside));
        self::assertInstanceOf(\Crab\Contracts\Storage\StorageException::class, $error);
    }

    public function testTheDefaultReportCoversYesterdayAndCountsOnlyPaidRevenue(): void
    {
        $user = $this->user('ada@example.com');
        foreach ([
            ['paid',      1000, '2026-01-31 00:00:00'],
            ['shipped',   2000, '2026-01-31 23:59:59'],
            ['pending',   3000, '2026-01-31 12:00:00'],
            ['cancelled', 4000, '2026-01-31 12:00:00'],
            ['paid',      5000, '2026-01-30 23:59:59'],
            ['paid',      6000, '2026-02-01 00:00:00'],
        ] as [$status, $total, $created]) {
            $this->db
                ->table('orders')
                ->insert([
                    'user_id' => $user->id,
                    'status' => $status,
                    'total' => $total,
                    'created_at' => $created,
                    'updated_at' => $created,
                ]);
        }

        $this->app->container->get(BuildDailyReport::class)->handle();
        $report = json_decode(
            file_get_contents($this->directory . '/reports/2026-01-31.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame(
            [
                'day' => '2026-01-31',
                'orders' => 4,
                'paid' => 1,
                'shipped' => 1,
                'cancelled' => 1,
                'pending' => 1,
                'revenue' => 3000,
            ],
            $report,
        );
    }

    public function testShippingKeepsThePaymentInTheDailyReport(): void
    {
        $id = (int) $this->db
            ->table('orders')
            ->insert([
                'user_id' => $this->user('ada@example.com')->id,
                'status' => 'paid',
                'total' => 1000,
                'created_at' => '2026-01-31 12:00:00',
                'updated_at' => '2026-01-31 12:00:00',
            ]);
        $report = $this->app->container->get(BuildDailyReport::class);
        $report->handle('2026-01-31');
        $before = json_decode(
            file_get_contents($this->directory . '/reports/2026-01-31.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
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
        $after = json_decode(
            file_get_contents($this->directory . '/reports/2026-01-31.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame(1000, $after['revenue']);
        self::assertSame([1, 0, 1, 0, 0], [
            $after['orders'],
            $after['paid'],
            $after['shipped'],
            $after['pending'],
            $after['cancelled'],
        ]);
        $this->api->actingAs($this->user('ada@example.com'))->get("/orders/{$id}")->assertJson('status', 'shipped');
        self::assertSame(
            'shipped',
            $this->api->actingAs($this->user('ada@example.com'))->get('/orders')->json('data.0.status'),
        );
    }
}
