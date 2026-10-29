<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mailer;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Runs\Run;

/**
 * What happens to an order after it is placed, over days: it waits for
 * the order to ship, lets it arrive, then asks the customer for a review.
 * A run, so a deploy or a crash in the middle loses nothing, and no
 * worker is held while it waits. ShipOrder gives it the "shipped" signal.
 *
 *     php cua runs:list
 */
final readonly class FollowUpOrder
{
    /**
     * How long an order takes to arrive, before asking about it.
     */
    private const ARRIVAL = 3 * 86400;

    public function __construct(
        private Database $db,
        private Mailer $mailer,
    ) {
    }

    /**
     * @return array{order: int, shipped: string, asked: bool}
     */
    public function handle(Run $run, int $orderId): array
    {
        $shipped = $run->await('shipped');
        $run->sleep('arrival', self::ARRIVAL);

        // In a step: it is sent once, however often the run is resumed after it.
        $asked = $run->step('ask-for-review', function () use ($orderId): bool {
            $order = $this->db->table('orders')->find($orderId);
            $user = $order === null ? null : $this->db->table('users')->find($order['user_id']);
            if ($user === null) {
                return false;
            }
            $this->mailer->send(
                (string) $user['email'],
                "How is your order #{$orderId}?",
                "Hello {$user['name']},\n\nYour order should have arrived. Tell us how you like it.\n",
            );
            return true;
        });

        return ['order' => $orderId, 'shipped' => (string) $shipped['at'], 'asked' => $asked];
    }
}
