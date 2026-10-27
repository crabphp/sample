<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Http\Response;

/**
 * The whole catalogue as a spreadsheet: GET /products.csv. It is what
 * `shop:mirror-catalogue` downloads, standing in for a supplier's feed.
 */
final readonly class ExportProducts
{
    public function __construct(private Database $db)
    {
    }

    public function handle(): Response
    {
        $csv = fopen('php://temp', 'w+b');
        fputcsv($csv, ['sku', 'name', 'category', 'price', 'stock'], escape: '');
        foreach ($this->db->table('products')->select('sku', 'name', 'category', 'price', 'stock')->orderBy('sku')->get() as $product) {
            fputcsv($csv, array_values($product), escape: '');
        }
        rewind($csv);

        return Response::attachment((string) stream_get_contents($csv), 'products.csv', 'text/csv; charset=utf-8');
    }
}
