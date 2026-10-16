<?php

declare(strict_types=1);

namespace App\Actions;

use App\Mailer;
use Crab\Contracts\Database\Database;

/**
 * Writes to the customer that their order was received. A mail server
 * that refuses it makes the job fail, and the queue tries it again.
 */
final readonly class SendOrderConfirmation
{
    public function __construct(
        private Database $db,
        private Mailer $mailer,
    ) {
    }

    public function handle(int $orderId): void
    {
        $order = $this->db->table('orders')->find($orderId);
        if ($order === null) {
            // Deleted since; there is no one to write to, and trying again would not change that.
            return;
        }

        $user = $this->db->table('users')->find($order['user_id']);
        $lines = $this->db->table('order_items')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('order_items.order_id', $orderId)
            ->select('products.name', 'order_items.quantity', 'order_items.price')
            ->get();

        $body = "Hello {$user['name']},\n\nWe have your order #{$orderId}:\n\n";
        foreach ($lines as $line) {
            $quantity = (int) $line['quantity'];
            $total = self::money((int) $line['price'] * $quantity);
            $body .= sprintf("  %d x %s  %s\n", $quantity, $line['name'], $total);
        }
        $body .= "\nTotal: " . self::money((int) $order['total']) . "\n";

        $this->mailer->send((string) $user['email'], "Your order #{$orderId}", $body);
    }

    private static function money(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
}
