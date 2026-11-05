<?php

declare(strict_types=1);

namespace App\Console;

use App\Actions\BuildDailyReport;
use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;

final readonly class Report implements Command
{
    public function __construct(
        private BuildDailyReport $report,
    ) {}

    public function description(): string
    {
        return 'Write a daily report to shop.report_disk; --day=2026-01-31, or yesterday when omitted';
    }

    public function handle(Input $input, Output $output): int
    {
        $this->report->handle($input->value('day', '2026-01-31'));
        $output->line('Report written to disk ' . config('shop.report_disk') . '.');
        return 0;
    }
}
