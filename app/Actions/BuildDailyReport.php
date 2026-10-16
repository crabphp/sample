<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Psr\Clock\ClockInterface;

/**
 * Adds up a day's orders and writes them to a file, for whoever reads
 * the numbers in the morning.
 */
final readonly class BuildDailyReport
{
    public function __construct(
        private Database $db,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param string|null $day The day to report, as "2026-01-31"; yesterday when left out.
     */
    public function handle(?string $day = null): void
    {
        $day ??= $this->clock->now()->modify('-1 day')->format('Y-m-d');
        $totals = $this->db->table('orders')
            ->select('status')
            ->selectRaw('COUNT(*) as orders, SUM(total) as revenue')
            ->whereBetween('created_at', "{$day} 00:00:00", "{$day} 23:59:59")
            ->groupBy('status')
            ->get();
        $report = [
            'day' => $day,
            'orders' => 0,
            'paid' => 0,
            'shipped' => 0,
            'cancelled' => 0,
            'pending' => 0,
            'revenue' => 0,
        ];

        foreach ($totals as $total) {
            $status = $total['status'];
            $count = (int) $total['orders'];
            $report['orders'] += $count;
            $report[$status] += $count;

            if (in_array($status, ['paid', 'shipped'], true)) {
                $report['revenue'] += (int) $total['revenue'];
            }
        }

        $directory = (string) config('shop.reports');
        if (!is_dir($directory) && !@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            throw new \RuntimeException("The report folder {$directory} cannot be created.");
        }

        $file = "{$directory}/{$day}.json";
        $contents = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        if (@file_put_contents($file, $contents) !== strlen($contents)) {
            throw new \RuntimeException("The report {$file} could not be written.");
        }
    }
}
