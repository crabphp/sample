<?php

declare(strict_types=1);

namespace Tests;

use Crab\Console\Output;
use Crab\Contracts\HttpClient\Client;
use Crab\HttpClient\FakeClient;
use PHPUnit\Framework\Attributes\DataProvider;

final class CommandInputTest extends ShopTestCase
{
    #[DataProvider('invalidOptions')]
    public function testInvalidOptionsSendNoRequests(string $command, array $options, string $problem): void
    {
        $http = new FakeClient(['*' => 503]);
        $this->app->container->instance(Client::class, $http);
        $stream = fopen('php://memory', 'w+');
        try {
            $status = $this->app->console->run([$command, ...$options], new Output($stream, $stream));
            rewind($stream);
            $output = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
        self::assertSame(1, $status);
        self::assertSame([], $http->requests, 'Invalid input must be rejected before logging in or downloading.');
        self::assertStringContainsString($problem, $output);
    }

    public static function invalidOptions(): iterable
    {
        yield 'missing duration' => ['shop:traffic', ['--seconds'], '--seconds=30'];
        yield 'partial duration' => ['shop:traffic', ['--seconds=2days'], '--seconds=30'];
        yield 'zero duration' => ['shop:traffic', ['--seconds=0'], '--seconds=30'];
        yield 'overflow duration' => ['shop:traffic', ['--seconds=99999999999999999999'], '--seconds=30'];
        yield 'missing rate' => ['shop:traffic', ['--rate'], '--rate=200'];
        yield 'negative rate' => ['shop:traffic', ['--rate=-1'], '--rate=200'];
        yield 'fractional rate' => ['shop:traffic', ['--rate=1.5'], '--rate=200'];
        yield 'missing value first' => ['shop:traffic', ['--seconds=bad', '--rate'], '--rate needs a value'];
        yield 'traffic address' => ['shop:traffic', ['--url'], '--url=http://127.0.0.1:8080'];
        yield 'mirror address' => ['shop:mirror-catalogue', ['--url'], '--url=http://127.0.0.1:8080'];
        yield 'missing pause' => ['shop:dispatch', ['--pause'], '--pause=3'];
        yield 'missing seed value first' => [
            'shop:seed',
            ['--fresh', '--products=bad', '--users'],
            '--users needs a value',
        ];
    }
}
