<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mailer;
use App\Models\Order;

/**
 * Writes to the customer that their order was received. A mail server
 * that refuses it makes the job fail, and the queue tries it again.
 */
final readonly class SendOrderConfirmation
{
    public function __construct(
        private Mailer $mailer,
    ) {}

    public function handle(int $orderId): void
    {
        $order = Order::query()->with('lines.product', 'customer')->find($orderId);
        $customer = $order?->customer;
        if ($order === null || $customer === null) {
            // Deleted since; there is no one to write to, and trying again would not change that.
            return;
        }

        $body = "Hello {$customer->name},\n\nWe have your order #{$orderId}:\n\n";
        foreach ($order->lines as $line) {
            if ($line->product === null) {
                continue;
            }
            $body .= sprintf("  %d x %s  %s\n", $line->quantity, $line->product->name, self::money($line->subtotal()));
        }
        $body .= "\nTotal: " . self::money($order->total) . "\n";

        $this->mailer->send($customer->email, "Your order #{$orderId}", $body);
    }

    private static function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
