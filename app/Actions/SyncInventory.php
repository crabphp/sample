<?php

declare(strict_types=1);

namespace App\Actions;

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
    public function __construct(
        private Client $http,
        private LoggerInterface $log,
    ) {
    }

    /**
     * @param list<int> $productIds
     */
    public function handle(int $orderId, array $productIds): void
    {
        $warehouse = (string) config('shop.warehouse_url');
        if ($warehouse !== '') {
            $http = $this->http->timeout(5);
            foreach ($productIds as $productId) {
                // A failed response retries the job, just like a failed connection.
                $http->get("{$warehouse}/products/{$productId}")->throw();
            }
        }

        $this->log->info('Order {order} was synced with the warehouse.', ['order' => $orderId, 'products' => count($productIds)]);
    }
}
