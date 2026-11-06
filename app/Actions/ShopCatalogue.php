<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\ProductSearch;
use App\User;
use Crab\Contracts\Database\Page;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\Routing\FromQuery;

/**
 * The Arc catalogue page uses the same paged product and category actions as
 * the JSON API, keeping filters and counts identical between both surfaces.
 */
final readonly class ShopCatalogue
{
    public function __construct(
        private ListProducts $products,
        private ListCategories $categories,
    ) {}

    #[ApiResponse(200, description: 'The HTML catalogue.', contentType: 'text/html')]
    #[ApiResponse(422, body: ['type' => 'string'], description: 'The filters are invalid.', contentType: 'text/html')]
    public function handle(#[FromQuery] ProductSearch $search, ?User $user): Response
    {
        // A normal GET form includes the empty "All" options. They mean no filter.
        $search = new ProductSearch(
            q: $search->q,
            category: $search->category === '' ? null : $search->category,
            colour: $search->colour === '' ? null : $search->colour,
            minPrice: $search->minPrice,
            maxPrice: $search->maxPrice,
            page: $search->page,
            perPage: $search->perPage,
        );
        $page = $this->products->handle($search);
        $query = array_filter(
            [
                'q' => $search->q === '' ? null : $search->q,
                'category' => $search->category,
                'colour' => $search->colour,
                'minPrice' => $search->minPrice,
                'maxPrice' => $search->maxPrice,
                'perPage' => $search->perPage,
            ],
            static fn(mixed $value): bool => $value !== null && $value !== '',
        );

        return view('shop.catalogue', [
            'user' => $user,
            'page' => $page,
            'categories' => $this->categories->handle(),
            'search' => $search,
            'query' => $query,
            'pagination' => self::pagination($page, $query),
        ]);
    }

    /**
     * @param array<string, int|string> $query
     * @return list<array{number: int, url: string}>
     */
    private static function pagination(Page $page, array $query): array
    {
        if ($page->lastPage === null) {
            return [];
        }
        $first = max(1, $page->currentPage - 2);
        $last = min($page->lastPage, $page->currentPage + 2);
        $links = [];
        for ($number = $first; $number <= $last; ++$number) {
            $links[] = [
                'number' => $number,
                'url' => '/shop?' . http_build_query([...$query, 'page' => $number]),
            ];
        }
        return $links;
    }
}
