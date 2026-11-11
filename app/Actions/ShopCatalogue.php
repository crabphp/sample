<?php

declare(strict_types=1);

namespace App\Actions;

use App\Http\ShopProductSearch;
use App\Models\Product;
use App\Shop\Pages;
use App\User;
use Crab\Contracts\Database\Page;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;
use Crab\Routing\FromQuery;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The Arc catalogue page uses the same paged product and category actions as
 * the JSON API, keeping filters and counts identical between both surfaces.
 */
final readonly class ShopCatalogue
{
    public function __construct(
        private Pages $pages,
        private ListCategories $categories,
    ) {}

    #[ApiResponse(200, body: ['type' => 'string'], description: 'The HTML catalogue.', contentType: 'text/html')]
    #[ApiResponse(422, body: ['type' => 'string'], description: 'The filters are invalid.', contentType: 'text/html')]
    public function handle(
        ServerRequestInterface $request,
        #[FromQuery]
        ShopProductSearch $search,
        ?User $user,
    ): Response {
        $inCents = $search->inCents();
        $page = Product::search($inCents)->paginate($inCents->perPage, $inCents->page);
        $query = array_filter(
            [
                'q' => $search->q === '' ? null : $search->q,
                'category' => $search->category,
                'colour' => $search->colour,
                'minPrice' => $search->minPrice,
                'maxPrice' => $search->maxPrice,
                'perPage' => $search->perPage,
                'sort' => $search->sort,
            ],
            static fn(mixed $value): bool => $value !== null && $value !== '',
        );

        return $this->pages->render('shop.catalogue', $request, $user, [
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
    public static function pagination(Page $page, array $query, string $path = '/products'): array
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
                'url' => $path . '?' . http_build_query([...$query, 'page' => $number]),
            ];
        }
        return $links;
    }
}
