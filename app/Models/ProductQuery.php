<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\ProductSearch;
use Crab\Tide\ModelQuery;

/**
 * Every query of products: Tide makes Product::query() one, as it is named after the model beside it.
 *
 * @extends ModelQuery<Product>
 */
final class ProductQuery extends ModelQuery
{
    /**
     * The products a search asks for, in its order: the shop's pages and the API read the same.
     */
    public function matching(ProductSearch $search): static
    {
        if ($search->q !== '') {
            $this->where('name', 'like', '%' . $search->q . '%');
        }
        if ($search->category !== null) {
            $this->where('category', $search->category);
        }
        if ($search->colour !== null) {
            // MySQL unquotes JSON null to the text "null" when compared as a string.
            $this->whereNotNull('details->colour')->where('details->colour', $search->colour);
        }
        if ($search->minPrice !== null) {
            $this->where('price', '>=', $search->minPrice);
        }
        if ($search->maxPrice !== null) {
            $this->where('price', '<=', $search->maxPrice);
        }
        return $this->orderBy(
            match ($search->sort) {
                'price-asc', 'price-desc' => 'price',
                'newest' => 'id',
                default => 'name',
            },
            in_array($search->sort, ['price-desc', 'newest'], true) ? 'desc' : 'asc',
        )->orderBy('id');
    }
}
