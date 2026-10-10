<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Database\Database;
use Crab\Contracts\HttpClient\Client;
use Psr\Log\LoggerInterface;

/**
 * Tells the warehouse what an order took. The warehouse here is the
 * sample itself, asked over HTTP for each product's stock, which stands
 * in for another company's API: the worker goes on with other jobs while
 * it waits for the answer.
 */
final readonly class SyncInventory
{
    public function __construct(private Database $db, private Client $http, private LoggerInterface $log)
    {
    }

    /**
     * @param list<int> $productIds
     */
    public function handle(int $orderId, array $productIds): void
    {
        $warehouse = (string) config('shop.warehouse_url');
        foreach ($productIds as $productId) {
            if ($warehouse === '') {
                continue;
            }
            // Throws when the warehouse answers with an error, and the job is tried again.
            $this->http->timeout(5)->get("{$warehouse}/products/{$productId}")->throw();
        }
        $this->log->info('Order {order} was synced with the warehouse.', ['order' => $orderId, 'products' => count($productIds)]);
    }
}
