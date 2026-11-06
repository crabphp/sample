<?php

declare(strict_types=1);

namespace Tests;

use App\AssistantModel;
use Crab\Config\SettingsException;
use Crab\Contracts\Config\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
    #[DataProvider('modelAddresses')]
    public function testModelUrlFollowsTheApplicationAddressUnlessOverridden(
        string $serverAddress,
        ?string $appUrl,
        ?string $modelUrl,
        string $expected,
    ): void {
        $before = [];
        foreach ([
            'APP_URL' => $appUrl,
            'MODEL_URL' => $modelUrl,
            'SERVER_ADDRESS' => $serverAddress,
        ] as $name => $value) {
            $before[$name] = getenv($name);
            // Explicit null also masks any setting in the developer's .env.
            putenv($name . '=' . ($value ?? 'null'));
        }
        try {
            $app = require dirname(__DIR__) . '/app.php';
            $app->config->check('app', 'shop', 'server');
            self::assertSame($expected . '/api/model/chat', AssistantModel::url());
            if ($modelUrl === null) {
                self::assertSame($expected, rtrim($app->config->get('app.url'), '/'));
            }
        } finally {
            foreach ($before as $name => $value) {
                putenv($value === false ? $name : "{$name}={$value}");
            }
        }
    }

    public static function modelAddresses(): iterable
    {
        yield 'development port' => ['tcp://127.0.0.1:8091', null, null, 'http://127.0.0.1:8091'];
        yield 'another port' => ['tcp://127.0.0.1:9123', null, null, 'http://127.0.0.1:9123'];
        yield 'all IPv4 interfaces' => ['tcp://0.0.0.0:9123', null, null, 'http://127.0.0.1:9123'];
        yield 'all IPv6 interfaces' => ['tcp://[::]:9123', null, null, 'http://[::1]:9123'];
        yield 'IPv6 loopback' => ['tcp://[::1]:9123', null, null, 'http://[::1]:9123'];
        yield 'public app URL' => [
            'tcp://127.0.0.1:9123',
            'https://shop.example.test/',
            null,
            'https://shop.example.test',
        ];
        yield 'real model override' => [
            'tcp://127.0.0.1:9123',
            'https://shop.example.test',
            'https://model.example.test/base/',
            'https://model.example.test/base',
        ];
    }

    #[DataProvider('invalidEnvironment')]
    public function testAnInvalidEnvironmentValueIsNotSilentlyCoerced(string $name, string $value): void
    {
        $before = getenv($name);
        putenv("{$name}={$value}");
        try {
            $this->expectException(SettingsException::class);
            $app = require dirname(__DIR__) . '/app.php';
            $app->container->get(Config::class)->check();
        } finally {
            putenv($before === false ? $name : "{$name}={$before}");
        }
    }

    public static function invalidEnvironment(): iterable
    {
        yield 'workers' => ['SERVER_WORKERS', '2workers'];
        yield 'async' => ['DB_ASYNC', 'sometimes'];
        yield 'mail probability' => ['MAIL_FAIL_RATE', 'never'];
        yield 'mail range' => ['MAIL_FAIL_RATE', '2'];
        yield 'login limit' => ['LOGIN_LIMIT', '1.5'];
        yield 'register limit' => ['REGISTER_LIMIT', '10requests'];
        yield 'concurrency' => ['MAX_REQUESTS', 'true'];
    }
}
