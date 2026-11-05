<?php

declare(strict_types=1);

namespace Tests;

use Crab\Config\SettingsException;
use Crab\Contracts\Config\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConfigurationTest extends TestCase
{
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
