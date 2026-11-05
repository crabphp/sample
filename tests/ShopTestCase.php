<?php

declare(strict_types=1);

namespace Tests;

use App\User;
use Crab\App;
use Crab\Console\Output;
use Crab\Contracts\Clock\Clock;
use Crab\Contracts\Database\Database;
use Crab\Testing\Api;
use Crab\Testing\FrozenClock;
use PHPUnit\Framework\TestCase;

/**
 * Gives each shop test its own database and output folders, and an API
 * client that sends requests through the real application.
 */
abstract class ShopTestCase extends TestCase
{
    protected App $app;
    protected Api $api;
    protected Database $db;
    protected string $directory;
    protected FrozenClock $clock;
    private array $environment = [];
    private ?\PDO $mysql = null;
    private ?string $mysqlDatabase = null;
    private ?string $redisPrefix = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crab-sample-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/mail', recursive: true);
        $dsn = 'sqlite:' . $this->directory . '/shop.sqlite';
        if (($server = getenv('SAMPLE_TEST_MYSQL_DSN')) !== false) {
            $this->mysql = new \PDO(
                $server,
                getenv('SAMPLE_TEST_MYSQL_USER') ?: 'root',
                getenv('SAMPLE_TEST_MYSQL_PASSWORD') ?: '',
            );
            $this->mysqlDatabase = 'crab_sample_' . bin2hex(random_bytes(8));
            $this->mysql->exec('CREATE DATABASE ' . $this->mysqlDatabase . ' CHARACTER SET utf8mb4');
            $dsn = $server . ';dbname=' . $this->mysqlDatabase;
        }
        $redis = getenv('SAMPLE_TEST_REDIS_URL');
        $this->redisPrefix = $redis === false ? null : 'sample-test:' . bin2hex(random_bytes(8)) . ':';
        // The real environment is read before .env, so a test can have a shop of its own.
        foreach ([
            'DB_DSN' => $dsn,
            'DB_USERNAME' => $this->mysql === null ? '' : (getenv('SAMPLE_TEST_MYSQL_USER') ?: 'root'),
            'DB_PASSWORD' => $this->mysql === null ? '' : (getenv('SAMPLE_TEST_MYSQL_PASSWORD') ?: ''),
            'DB_ASYNC' => 'false',
            'CACHE_STORE' => $redis === false ? 'array' : 'redis',
            'QUEUE_STORE' => $redis === false ? 'database' : 'redis',
            'REDIS_URL' => $redis === false ? 'redis://127.0.0.1:6379' : $redis,
            'REDIS_PREFIX' => $this->redisPrefix ?? 'shop:',
            'BROADCAST_CARRIER' => 'local',
            'LOG_PATH' => $this->directory . '/app.log',
            'MAIL_PATH' => $this->directory . '/mail',
            'MAIL_FAIL_RATE' => '0',
            'REPORTS_PATH' => $this->directory . '/reports',
            'REPORTS_DRIVER' => 'local',
            'REPORTS_DISK' => 'reports',
            'CATALOGUE_PATH' => $this->directory . '/catalogue.csv',
            'WAREHOUSE_URL' => '',
            'CLOCKWERK_TOKEN' => 'test-token',
        ] as $name => $value) {
            $this->environment[$name] = getenv($name);
            putenv("{$name}={$value}");
        }
        $this->app = require dirname(__DIR__) . '/app.php';
        $this->clock = new FrozenClock('2026-02-01 12:00:00 UTC');
        $this->app->container->instance(Clock::class, $this->clock);
        // The API fixture represents one serving HTTP worker, including local broadcasts.
        $this->app->container->get(\Crab\Broadcast\Lifecycle::class)->serving = true;
        self::assertSame(0, $this->command('migrate'));
        $this->db = $this->app->container->get(Database::class);
        $this->createFixtures();
        $this->api = new Api($this->redisPrefix === null ? $this->app : new WorkerHandler($this->app));
    }

    protected function tearDown(): void
    {
        $this->api->closeSockets();
        if ($this->redisPrefix !== null) {
            $redis = $this->app->container->get(\Crab\Redis\Client::class);
            $cursor = '0';
            do {
                [$cursor, $keys] = $redis->command('SCAN', $cursor, 'MATCH', $this->redisPrefix . '*', 'COUNT', 100);
                if ($keys !== []) {
                    $redis->command('DEL', ...$keys);
                }
            } while ((string) $cursor !== '0');
            $redis->close();
        }
        if ($this->mysql !== null) {
            $this->mysql->exec('DROP DATABASE ' . $this->mysqlDatabase);
            $this->mysql = null;
        }
        foreach ([...(glob($this->directory . '/*/*') ?: []), ...(glob($this->directory . '/*.*') ?: [])] as $file) {
            unlink($file);
        }
        foreach (glob($this->directory . '/*', GLOB_ONLYDIR) ?: [] as $folder) {
            rmdir($folder);
        }
        rmdir($this->directory);
        foreach ($this->environment as $name => $value) {
            putenv($value === false ? $name : "{$name}={$value}");
        }
    }

    /**
     * Loads a shop user for actingAs(), without issuing a token when a
     * test concerns what that user can do rather than how they log in.
     */
    protected function user(string $email): User
    {
        $row = $this->db->table('users')->where('email', $email)->first();
        if ($row === null) {
            throw new \LogicException("The shop fixture has no user {$email}. Use an account from the fixture.");
        }
        return User::from($row);
    }

    /**
     * A few known rows for API behavior, independent of the demo data.
     * Historical paid orders exercise ownership without scheduling work.
     */
    private function createFixtures(): void
    {
        static $password = null;
        $password ??= password_hash('password', PASSWORD_BCRYPT, ['cost' => 4]);
        $timestamps = ['created_at' => '2026-01-01 12:00:00', 'updated_at' => '2026-01-01 12:00:00'];
        $this->db
            ->table('users')
            ->insertMany([
                [
                    'id' => 1,
                    'name' => 'Admin',
                    'email' => 'admin@example.com',
                    'password' => $password,
                    'role' => 'admin',
                    ...$timestamps,
                ],
                [
                    'id' => 2,
                    'name' => 'Ada',
                    'email' => 'ada@example.com',
                    'password' => $password,
                    'role' => 'member',
                    ...$timestamps,
                ],
                [
                    'id' => 3,
                    'name' => 'Grace',
                    'email' => 'grace@example.com',
                    'password' => $password,
                    'role' => 'member',
                    ...$timestamps,
                ],
            ]);
        $this->db
            ->table('products')
            ->insertMany([
                [
                    'id' => 1,
                    'sku' => 'KIT-001',
                    'name' => 'Stoneware Mug',
                    'category' => 'kitchen',
                    'price' => 1400,
                    'stock' => 30,
                    'details' => '{"colour": "blue", "dishwasherSafe": true}',
                    ...$timestamps,
                ],
                [
                    'id' => 2,
                    'sku' => 'KIT-002',
                    'name' => 'Pour-over Kettle',
                    'category' => 'kitchen',
                    'price' => 6200,
                    'stock' => 20,
                    'details' => '{"colour": "black"}',
                    ...$timestamps,
                ],
                [
                    'id' => 3,
                    'sku' => 'DSK-001',
                    'name' => 'Grid Notebook',
                    'category' => 'desk',
                    'price' => 800,
                    'stock' => 50,
                    'details' => null,
                    ...$timestamps,
                ],
                [
                    'id' => 4,
                    'sku' => 'DSK-002',
                    'name' => 'Desk Lamp',
                    'category' => 'desk',
                    'price' => 4200,
                    'stock' => 10,
                    'details' => '{"colour": "black"}',
                    ...$timestamps,
                ],
                [
                    'id' => 5,
                    'sku' => 'HOM-001',
                    'name' => 'Cotton Blanket',
                    'category' => 'home',
                    'price' => 8900,
                    'stock' => 12,
                    'details' => '{"colour": "blue"}',
                    ...$timestamps,
                ],
                [
                    'id' => 6,
                    'sku' => 'HOM-002',
                    'name' => 'Linen Cushion',
                    'category' => 'home',
                    'price' => 2500,
                    'stock' => 0,
                    'details' => '{"colour": null}',
                    ...$timestamps,
                ],
            ]);
        $this->db
            ->table('orders')
            ->insertMany([
                ['id' => 1, 'user_id' => 2, 'status' => 'paid', 'total' => 1400, ...$timestamps],
                ['id' => 2, 'user_id' => 3, 'status' => 'paid', 'total' => 6200, ...$timestamps],
            ]);
        $this->db
            ->table('order_items')
            ->insertMany([
                ['order_id' => 1, 'product_id' => 1, 'quantity' => 1, 'price' => 1400],
                ['order_id' => 2, 'product_id' => 2, 'quantity' => 1, 'price' => 6200],
            ]);
    }

    /**
     * Runs an application command and captures its output in memory.
     */
    protected function command(string ...$words): int
    {
        $stream = fopen('php://memory', 'w+');
        try {
            if ($this->redisPrefix !== null && $words[0] !== 'queue:work') {
                return WorkerHandler::run(fn(): int => $this->app->console->run($words, new Output($stream, $stream)));
            }
            return $this->app->console->run($words, new Output($stream, $stream));
        } finally {
            fclose($stream);
        }
    }
}
