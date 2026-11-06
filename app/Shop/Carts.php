<?php

declare(strict_types=1);

namespace App\Shop;

use App\Actions\PlaceOrder;
use App\Http\OrderInput;
use App\User;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Validation\Validator;
use Crab\Http\HttpException;
use Psr\Clock\ClockInterface;
use Psr\Http\Message\ServerRequestInterface;

/** Durable shopping data, with no session or worker-local state. */
final readonly class Carts
{
    public function __construct(
        private Database $db,
        private ClockInterface $clock,
        private Validator $validator,
    ) {}

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        return (
            substr($hex, 0, 8)
            . '-'
            . substr($hex, 8, 4)
            . '-'
            . substr($hex, 12, 4)
            . '-'
            . substr($hex, 16, 4)
            . '-'
            . substr($hex, 20)
        );
    }

    public function key(ServerRequestInterface $request, ?User $user): ?string
    {
        if ($user !== null) {
            return 'user:' . $user->id;
        }
        $id = BrowserCookies::read($request, 'shop_cart');
        if (preg_match('/^[a-f0-9]{64}$/D', $id) !== 1) {
            return null;
        }
        $key = 'guest:' . $id;
        return $this->db
            ->table('carts')
            ->where('id', $key)
            ->where('expires_at', '>', $this->clock->now()->getTimestamp())
            ->exists()
            ? $key
            : null;
    }

    public function snapshot(?string $key): array
    {
        $row = $key === null ? null : $this->db->table('carts')->where('id', $key)->first();
        $quantities = $row === null ? [] : json_decode($row['lines'], true, flags: JSON_THROW_ON_ERROR);
        $lines = [];
        $total = 0;
        $count = 0;
        foreach ($quantities as $id => $quantity) {
            $product = $this->db->table('products')->find((int) $id);
            $subtotal = (int) ($product['price'] ?? 0) * $quantity;
            $lines[] = ['id' => (int) $id, 'quantity' => $quantity, 'product' => $product, 'subtotal' => $subtotal];
            $total += $subtotal;
            $count += $quantity;
        }
        return ['lines' => $lines, 'total' => $total, 'count' => $count, 'checkoutId' => $row['checkout_id'] ?? ''];
    }

    public function change(string $key, int $id, int $quantity, bool $add, string $actionId): void
    {
        $this->db->transaction(function (Connection $db) use ($key, $id, $quantity, $add, $actionId): void {
            $cart = $this->lock($db, $key);
            $operation = hash('sha256', $key . ':' . $actionId);
            if ($db->table('cart_operations')->where('id', $operation)->exists()) {
                return;
            }
            $lines = json_decode($cart['lines'], true, flags: JSON_THROW_ON_ERROR);
            if ($quantity > 0) {
                if (!$db->table('products')->where('id', $id)->exists()) {
                    throw new HttpException(404, 'This product is no longer available.');
                }
                $quantity += $add ? $lines[$id] ?? 0 : 0;
                if ($quantity > 20 || !isset($lines[$id]) && count($lines) >= 20) {
                    throw new HttpException(422, 'A cart can hold 20 products, with up to 20 of each.');
                }
                $lines[$id] = $quantity;
            } else {
                unset($lines[$id]);
            }
            $this->save($db, $key, $lines);
            $db->table('cart_operations')->insert(['id' => $operation, 'expires_at' => $this->expires()]);
        });
    }

    public function merge(ServerRequestInterface $request, User $user): void
    {
        $guest = $this->key($request, null);
        if ($guest === null) {
            return;
        }
        $this->db->transaction(function (Connection $db) use ($guest, $user): void {
            $key = 'user:' . $user->id;
            $cart = $this->lock($db, $key);
            $old = $db->table('carts')->where('id', $guest)->lockForUpdate()->first();
            if ($old === null) {
                return;
            }
            $lines = json_decode($cart['lines'], true, flags: JSON_THROW_ON_ERROR);
            foreach (json_decode($old['lines'], true, flags: JSON_THROW_ON_ERROR) as $id => $quantity) {
                $lines[$id] = ($lines[$id] ?? 0) + $quantity;
            }
            // Preserve every item, even if the merged cart needs editing to meet order limits.
            $this->save($db, $key, $lines);
            $db->table('carts')->where('id', $guest)->delete();
        });
    }

    public function checkout(User $user, string $id, PlaceOrder $orders): int
    {
        $result = $this->db->transaction(function (Connection $db) use ($user, $id, $orders): array {
            $key = 'user:' . $user->id;
            $cart = $this->lock($db, $key);
            $previous = $db->table('cart_checkouts')->where('id', $id)->where('user_id', $user->id)->first();
            if ($previous !== null) {
                return ['id' => (int) $previous['order_id'], 'placed' => null];
            }
            if (!hash_equals($cart['checkout_id'], $id)) {
                throw new HttpException(
                    409,
                    'Your cart changed. Review the updated summary before placing your order.',
                );
            }
            $lines = [];
            foreach (json_decode($cart['lines'], true, flags: JSON_THROW_ON_ERROR) as $productId => $quantity) {
                $lines[] = ['productId' => (int) $productId, 'quantity' => $quantity];
            }
            $input = $this->validator->validate(OrderInput::class, ['lines' => $lines]);
            $placed = $orders->within($db, $user, $input);
            $db->table('cart_checkouts')->insert(['id' => $id, 'user_id' => $user->id, 'order_id' => $placed['id']]);
            $this->save($db, $key, []);
            return ['id' => $placed['id'], 'placed' => $placed];
        });
        if ($result['placed'] !== null) {
            $orders->announce($user, $result['placed']);
        }
        return $result['id'];
    }

    private function lock(Connection $db, string $key): array
    {
        $db->table('carts')->insertOrIgnore([
            'id' => $key,
            'lines' => '{}',
            'checkout_id' => self::uuid(),
            'expires_at' => $this->expires(),
        ]);
        return $db->table('carts')->where('id', $key)->lockForUpdate()->first();
    }

    private function save(Connection $db, string $key, array $lines): void
    {
        $db
            ->table('carts')
            ->where('id', $key)
            ->update([
                'lines' => json_encode((object) $lines, JSON_THROW_ON_ERROR),
                'checkout_id' => self::uuid(),
                'expires_at' => $this->expires(),
            ]);
    }

    private function expires(): int
    {
        return $this->clock->now()->getTimestamp() + (30 * 86_400);
    }
}
