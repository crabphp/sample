<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;

/**
 * Adds up a day's orders and writes them to a file, for whoever reads
 * the numbers in the morning.
 */
final readonly class BuildDailyReport
{
    public function __construct(private Database $db)
    {
    }

    /**
     * @param string|null $day The day to report, as "2026-01-31"; yesterday when left out.
     */
    public function handle(?string $day = null): void
    {
        $day ??= date('Y-m-d', time() - 86400);
        $orders = $this->db->table('orders')
            ->whereBetween('created_at', "{$day} 00:00:00", "{$day} 23:59:59")
            ->get();
        $report = ['day' => $day, 'orders' => count($orders), 'paid' => 0, 'cancelled' => 0, 'pending' => 0, 'revenue' => 0];
        foreach ($orders as $order) {
            ++$report[$order['status']];
            $report['revenue'] += $order['status'] === 'paid' ? (int) $order['total'] : 0;
        }
        $directory = (string) config('shop.reports');
        if (!is_dir($directory)) {
            mkdir($directory, 0o775, true);
        }
        file_put_contents("{$directory}/{$day}.json", json_encode($report, JSON_PRETTY_PRINT) . "\n");
    }
}
