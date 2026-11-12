<?php

declare(strict_types=1);

namespace App\Console;

use App\Actions\ShipOrder;
use Crab\Contracts\Console\Command;
use Crab\Contracts\Console\Input;
use Crab\Contracts\Console\Output;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;

/**
 * The dispatch desk: hands paid orders to the courier, oldest first, one
 * at a time. A handoff takes a while and must not be cut in half by a
 * deploy, so `start` runs this as the server "dispatch", registered with
 * `reloads: false`: `reload` leaves it running, and SIGTERM, from
 * `restart` or `stop`, lets the handoff in hand finish first.
 */
final readonly class DispatchOrders implements Command
{
    public function __construct(
        private Database $db,
        private ShipOrder $ship,
    ) {}

    public function description(): string
    {
        return <<<'TEXT'
            Hand paid orders to the courier, one at a time, until stopped
            Options:
              --pause=3   Seconds a handoff takes, from 0 to 3600
            TEXT;
    }

    public function handle(Input $input, Output $output): int
    {
        $pause = $input->value('pause', '3') ?? '3';
        $pause = filter_var($pause, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 3600]]);
        if ($pause === false) {
            $output->error('--pause takes whole seconds from 0 to 3600; use --pause=3.');
            return 1;
        }
        $stopping = false;
        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, static function () use (&$stopping): void {
            $stopping = true;
        });

        while (!$stopping) {
            $order = $this->db
                ->current()
                ->table('orders')
                ->where('status', 'paid')
                ->orderBy('id')
                ->select('id')
                ->first();
            if ($order === null) {
                sleep(1);
                continue;
            }
            $output->line("Handing order {$order['id']} to the courier.");
            // The handoff; a SIGTERM meanwhile waits for it.
            $left = $pause;
            while ($left > 0) {
                $left = sleep($left);
            }
            try {
                $this->ship->handle((int) $order['id']);
                $output->line("Order {$order['id']} shipped.");
            } catch (HttpException $error) {
                // An administrator shipped or cancelled it meanwhile.
                $output->line("Order {$order['id']} skipped: {$error->getMessage()}");
            }
        }
        return 0;
    }
}
