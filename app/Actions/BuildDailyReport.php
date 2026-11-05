<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Contracts\Storage\Storage;
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
        private Storage $storage,
    ) {}

    /**
     * @param string|null $day The day to report, as "2026-01-31"; yesterday when left out.
     */
    public function handle(?string $day = null): void
    {
        $day ??= $this->clock->now()->modify('-1 day')->format('Y-m-d');
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/D', $day) === 1
            ? \DateTimeImmutable::createFromFormat('!Y-m-d', $day)
            : false;
        if ($date === false || $date->format('Y-m-d') !== $day) {
            throw new \InvalidArgumentException('The report day must be a valid date in YYYY-MM-DD form.');
        }
        $totals = $this->db
            ->table('orders')
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

        $contents = json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        $this->storage->disk(config('shop.report_disk'))->write("{$day}.json", $contents, 'application/json');
    }
}
