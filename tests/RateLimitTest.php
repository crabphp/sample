<?php

declare(strict_types=1);

namespace Tests;

use Crab\Contracts\Clock\Clock;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Exercises configurable limits through the real routes, including a
 * benchmark limit above the ordinary ten attempts per minute.
 */
final class RateLimitTest extends ShopTestCase
{
    private array $environment = [];

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            foreach ($this->environment as $name => $value) {
                putenv($value === false ? $name : "{$name}={$value}");
            }
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function limits(): iterable
    {
        yield 'independent small limits' => [2, 3];
        yield 'benchmark limits' => [12, 12];
    }

    #[DataProvider('limits')]
    public function testLoginAndRegistrationUseTheirConfiguredLimits(int $login, int $register): void
    {
        foreach (['LOGIN_LIMIT' => $login, 'REGISTER_LIMIT' => $register] as $name => $limit) {
            $this->environment[$name] = getenv($name);
            putenv("{$name}={$limit}");
        }
        // Routes are already loaded by setUp, so rebuild after setting
        // their environment, using the same isolated fixture database.
        $this->app = require dirname(__DIR__) . '/app.php';
        $this->app->container->instance(Clock::class, $this->clock);
        $this->api = new \Crab\Testing\Api($this->app);
        for ($try = 0; $try < $login; ++$try) {
            $this->api->post('/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(401);
        }
        $this->api->post('/login', ['email' => 'ada@example.com', 'password' => 'wrong'])->assertStatus(429);
        for ($try = 0; $try < $register; ++$try) {
            $this->api->post('/register', [])->assertStatus(422);
        }
        $this->api->post('/register', [])->assertStatus(429);
    }
}
