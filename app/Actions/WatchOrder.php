<?php

declare(strict_types=1);

namespace App\Actions;

use App\User;
use Crab\Contracts\Database\Database;
use Crab\Contracts\WebSocket\Socket;
use Crab\Http\HttpException;

/**
 * Lets an order's owner watch its status. Read after joining to cover
 * changes during the join; the version lets clients discard older state.
 */
final readonly class WatchOrder
{
    public function __construct(private Database $db)
    {
    }

    public function handle(Socket $socket, int $id, User $user): void
    {
        $order = $this->db->table('orders')->find($id);
        if ($order === null || (string) $order['user_id'] !== (string) $user->id) {
            throw new HttpException(404, 'No such order.');
        }
        $socket->join("orders.{$id}");
        $snapshot = $this->db->table('orders')->where('id', $id)->select('status', 'version')->first();
        $socket->send(['type' => 'ready', 'id' => $id, 'status' => $snapshot['status'], 'version' => (int) $snapshot['version']]);
        foreach ($socket as $message) {
            // This route pushes updates; it has no client commands.
        }
    }
}
