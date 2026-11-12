<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Product;
use Crab\Http\Response;
use Crab\OpenApi\Attributes\Response as ApiResponse;

/**
 * The whole catalogue as a spreadsheet: GET /api/products.csv. It is what
 * `shop:mirror-catalogue` downloads, standing in for a supplier's feed.
 */
final readonly class ExportProducts
{
    #[ApiResponse(
        200,
        body: ['type' => 'string'],
        description: 'The catalogue as CSV.',
        contentType: 'text/csv; charset=utf-8',
        headers: ['Content-Disposition' => 'Attachment; filename="products.csv"'],
    )]
    public function handle(): Response
    {
        $csv = fopen('php://temp', 'w+b');
        fputcsv($csv, ['sku', 'name', 'category', 'price', 'stock'], escape: '');
        // SKU is unique, so it can be the chunk cursor and keep the feed's existing order.
        Product::query()
            ->select('sku', 'name', 'category', 'price', 'stock')
            ->chunkById(
                100,
                static function (array $products) use ($csv): void {
                    foreach ($products as $product) {
                        fputcsv(
                            $csv,
                            [$product->sku, $product->name, $product->category, $product->price, $product->stock],
                            escape: '',
                        );
                    }
                },
                'sku',
            );
        rewind($csv);

        return Response::attachment((string) stream_get_contents($csv), 'products.csv', 'text/csv; charset=utf-8');
    }
}
