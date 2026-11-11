<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\ProductSearch;
use Crab\Tide\Model;
use Crab\Tide\ModelQuery;

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
    public ?string $details = null;

    /**
     * The products a search asks for, in its order: the shop's pages and the API read the same.
     *
     * @return ModelQuery<self>
     */
    public static function search(ProductSearch $search): ModelQuery
    {
        return self::query()
            ->when($search->q !== '', static fn(ModelQuery $query): ModelQuery => $query->where(
                'name',
                'like',
                '%' . $search->q . '%',
            ))
            ->when($search->category !== null, static fn(ModelQuery $query): ModelQuery => $query->where(
                'category',
                $search->category,
            ))
            // MySQL unquotes JSON null to the text "null" when compared as a string.
            ->when($search->colour !== null, static fn(ModelQuery $query): ModelQuery => $query->whereNotNull(
                'details->colour',
            )->where('details->colour', $search->colour))
            ->when($search->minPrice !== null, static fn(ModelQuery $query): ModelQuery => $query->where(
                'price',
                '>=',
                $search->minPrice,
            ))
            ->when($search->maxPrice !== null, static fn(ModelQuery $query): ModelQuery => $query->where(
                'price',
                '<=',
                $search->maxPrice,
            ))
            ->orderBy(
                match ($search->sort) {
                    'price-asc', 'price-desc' => 'price',
                    'newest' => 'id',
                    default => 'name',
                },
                in_array($search->sort, ['price-desc', 'newest'], true) ? 'desc' : 'asc',
            )
            ->orderBy('id');
    }

    public function inStock(): bool
    {
        return $this->stock > 0;
    }

    /**
     * As the API lists a product: what a list needs, and no more.
     *
     * @return array{id: int, sku: string, name: string, category: string, price: int, stock: int}
     */
    public function summary(): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'name' => $this->name,
            'category' => $this->category,
            'price' => $this->price,
            'stock' => $this->stock,
        ];
    }

    /**
     * As the API gives a time: as the database holds it, without an offset.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }
}
