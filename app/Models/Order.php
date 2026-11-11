<?php

declare(strict_types=1);

namespace App\Models;

use App\OrderStatus;
use Crab\Tide\BelongsTo;
use Crab\Tide\HasMany;
use Crab\Tide\Model;

/**
 * One order, as a model that knows its lines. ShowOrder reads one with
 * its lines and their products in three queries, with no join to write.
 */
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
    #[HasMany(OrderItem::class)]
    public array $lines;

    /**
     * Who placed it. Named for what it is here, so its key is said: user_id.
     */
    #[BelongsTo('user_id')]
    public ?Account $customer;

    /**
     * As the API gives a time: as the database holds it, without an offset.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
