<?php

declare(strict_types=1);

namespace App\Models;

use Crab\Tide\Model;

/**
 * One product, as a model: a row that is read, changed and saved.
 * SetStock changes one this way. Its table is named after the class, and
 * its times are kept for it: updated_at is written with every change.
 */
final class Product extends Model
{
    public int $id;
    public string $sku;
    public string $name;
    public string $category;
    // In cents.
    public int $price;
    public int $stock;
    public ?\DateTimeImmutable $created_at;
    public ?\DateTimeImmutable $updated_at;
    // JSON text as the database stores it, which is how the API gives it.
    public ?string $details;

    /**
     * As the API gives a product: its times as the database holds them, without an offset.
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
