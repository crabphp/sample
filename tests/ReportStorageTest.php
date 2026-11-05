<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\BuildDailyReport;
use Crab\App;
use Crab\Contracts\Clock\Clock;
use Crab\Contracts\HttpClient\Client;
use Crab\Contracts\Storage\Storage;
use Crab\HttpClient\FakeClient;

final class ReportStorageTest extends ShopTestCase
{
    public function testTheReportCanBeKeptOnAMemoryDisk(): void
    {
        $app = $this->reports('memory');
        $app->container->get(BuildDailyReport::class)->handle();
        $disk = $app->container->get(Storage::class)->disk('reports');

        self::assertSame('2026-01-31', json_decode($disk->contents('2026-01-31.json'), true)['day']);
        self::assertSame('application/json', $disk->file('2026-01-31.json')?->type);
        self::assertDirectoryDoesNotExist($this->directory . '/reports');
    }

    public function testTheS3PluginUploadsTheReportThroughTheHttpClient(): void
    {
        $app = $this->reports('s3');
        $http = new FakeClient(['PUT https://s3.example/shop/reports/2026-01-31.json' => 200]);
        $app->container->instance(Client::class, $http);

        $app->container->get(BuildDailyReport::class)->handle();

        self::assertCount(1, $http->requests);
        $request = $http->requests[0];
        self::assertStringStartsWith('AWS4-HMAC-SHA256 ', $request->getHeaderLine('Authorization'));
        self::assertSame('20260201T120000Z', $request->getHeaderLine('x-amz-date'));
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('2026-01-31', json_decode((string) $request->getBody(), true)['day']);
        self::assertDirectoryDoesNotExist($this->directory . '/reports');
    }

    private function reports(string $driver): App
    {
        $before = [];
        foreach ([
            'REPORTS_DRIVER' => $driver,
            'S3_KEY' => 'sample-test-key',
            'S3_SECRET' => 'sample-test-secret',
            'S3_BUCKET' => 'shop',
            'S3_REGION' => 'us-east-1',
            'S3_ENDPOINT' => 'https://s3.example',
        ] as $name => $value) {
            $before[$name] = getenv($name);
            putenv("{$name}={$value}");
        }
        try {
            $app = require dirname(__DIR__) . '/app.php';
            $app->container->instance(Clock::class, $this->clock);
            return $app;
        } finally {
            foreach ($before as $name => $value) {
                putenv($value === false ? $name : "{$name}={$value}");
            }
        }
    }
}
