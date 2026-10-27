<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\FakeClient;

/**
 * The catalogue as a feed, and the command that downloads one into a
 * file: whole, exactly as sent, and never larger than a feed should be.
 */
final class CatalogueFeedTest extends ShopTestCase
{
    public function testTheFeedIsDownloadedIntoItsFileAsSent(): void
    {
        $feed = $this->api->get('/products.csv')->assertStatus(200);
        self::assertStringStartsWith('text/csv', $feed->response->getHeaderLine('Content-Type'));
        $csv = (string) $feed->response->getBody();
        self::assertStringStartsWith("sku,name,category,price,stock\n", $csv);

        $http = new FakeClient(['GET https://supplier.example/products.csv' => FakeClient::response(200, $csv)]);
        $this->app->container->instance(Client::class, $http);
        $file = $this->directory . '/catalogue.csv';

        self::assertSame(0, $this->command('shop:mirror-catalogue', '--url=https://supplier.example'));
        self::assertSame($csv, file_get_contents($file));
    }

    public function testAFeedLargerThanACatalogueIsRefusedAndLeavesNothing(): void
    {
        $http = new FakeClient(['*' => FakeClient::response(200, str_repeat('x', 8 * 1024 * 1024 + 1))]);
        $this->app->container->instance(Client::class, $http);

        self::assertSame(1, $this->command('shop:mirror-catalogue', '--url=https://supplier.example'));
        self::assertFileDoesNotExist($this->directory . '/catalogue.csv');
        self::assertSame([], glob($this->directory . '/.catalogue.csv.*'), 'Nothing is left beside it either.');
    }
}
