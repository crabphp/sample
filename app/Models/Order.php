<?php

declare(strict_types=1);

namespace App\Models;

use App\OrderStatus;
use Crab\Tide\HasMany;
use Crab\Tide\Model;
use Crab\Tide\Table;

/**
 * One order, as a model that knows its lines. ShowOrder reads one with
 * its lines and their products in three queries, with no join to write.
 */
#[Table('orders')]
final class Order extends Model
{
    public int $id;
    public int $user_id;
    public OrderStatus $status;
    // In cents.
    public int $total;
    public ?\DateTimeImmutable $created_at;
    public ?\DateTimeImmutable $updated_at;
    // Counts every change, so that a client can tell which snapshot is newer.
    public int $version;
    public ?int $follow_up_run;

    /**
     * Loaded only when asked for: Order::query()->with('lines').
     *
     * @var list<OrderItem>
     */
    #[HasMany(OrderItem::class, 'order_id')]
    public array $lines;

    /**
     * As the API gives an order: its times as the database holds them, without an offset.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            ...parent::jsonSerialize(),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
