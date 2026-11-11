<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mailer;
use App\Models\Order;
use Crab\Contracts\Runs\Run;

/**
 * What happens to an order after it is placed, over days: it waits for
 * the order to ship, lets it arrive, then asks the customer for a review.
 * A run, so a deploy or a crash in the middle loses nothing, and no
 * worker is held while it waits. SignalOrderShipped delivers its signal.
 *
 *     php cua runs:list
 */
final readonly class FollowUpOrder
{
    /**
     * How long an order takes to arrive, before asking about it.
     */
    private const ARRIVAL = 3 * 86_400;

    public function __construct(
        private Mailer $mailer,
    ) {}

    /**
     * @return array{order: int, shipped: string, asked: bool}
     */
    public function handle(Run $run, int $orderId): array
    {
        $shipped = $run->await('shipped');
        $run->sleep('arrival', self::ARRIVAL);

        // A completed step is kept. A crash after sending but before keeping it can send mail again.
        $asked = $run->step('ask-for-review', function () use ($orderId): bool {
            $customer = Order::query()->with('customer')->find($orderId)?->customer;
            if ($customer === null) {
                return false;
            }

            $this->mailer->send(
                $customer->email,
                "How is your order #{$orderId}?",
                "Hello {$customer->name},\n\nYour order should have arrived. Tell us how you like it.\n",
            );

            return true;
        });

        return ['order' => $orderId, 'shipped' => (string) $shipped['at'], 'asked' => $asked];
    }
}
