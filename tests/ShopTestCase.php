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

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crab-sample-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/mail', recursive: true);
        // The real environment is read before .env, so a test can have a shop of its own.
        foreach ([
            'DB_DSN' => 'sqlite:' . $this->directory . '/shop.sqlite', 'CACHE_STORE' => 'array', 'QUEUE_STORE' => 'database',
            'BROADCAST_CARRIER' => 'local',
            'LOG_PATH' => $this->directory . '/app.log', 'MAIL_PATH' => $this->directory . '/mail', 'MAIL_FAIL_RATE' => '0',
            'REPORTS_PATH' => $this->directory . '/reports', 'CATALOGUE_PATH' => $this->directory . '/catalogue.csv', 'WAREHOUSE_URL' => '', 'CLOCKWERK_TOKEN' => 'test-token',
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
        $this->api = new Api($this->app);
    }

    protected function tearDown(): void
    {
        $this->api->closeSockets();
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
        $this->db->table('users')->insertMany([
            ['id' => 1, 'name' => 'Admin', 'email' => 'admin@example.com', 'password' => $password, 'role' => 'admin', ...$timestamps],
            ['id' => 2, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => $password, 'role' => 'member', ...$timestamps],
            ['id' => 3, 'name' => 'Grace', 'email' => 'grace@example.com', 'password' => $password, 'role' => 'member', ...$timestamps],
        ]);
        $this->db->table('products')->insertMany([
            ['id' => 1, 'sku' => 'KIT-001', 'name' => 'Stoneware Mug', 'category' => 'kitchen', 'price' => 1400, 'stock' => 30, 'details' => '{"colour": "blue", "dishwasherSafe": true}', ...$timestamps],
            ['id' => 2, 'sku' => 'KIT-002', 'name' => 'Pour-over Kettle', 'category' => 'kitchen', 'price' => 6200, 'stock' => 20, 'details' => '{"colour": "black"}', ...$timestamps],
            ['id' => 3, 'sku' => 'DSK-001', 'name' => 'Grid Notebook', 'category' => 'desk', 'price' => 800, 'stock' => 50, 'details' => null, ...$timestamps],
            ['id' => 4, 'sku' => 'DSK-002', 'name' => 'Desk Lamp', 'category' => 'desk', 'price' => 4200, 'stock' => 10, 'details' => '{"colour": "black"}', ...$timestamps],
            ['id' => 5, 'sku' => 'HOM-001', 'name' => 'Cotton Blanket', 'category' => 'home', 'price' => 8900, 'stock' => 12, 'details' => '{"colour": "blue"}', ...$timestamps],
            ['id' => 6, 'sku' => 'HOM-002', 'name' => 'Linen Cushion', 'category' => 'home', 'price' => 2500, 'stock' => 0, 'details' => '{"colour": null}', ...$timestamps],
        ]);
        $this->db->table('orders')->insertMany([
            ['id' => 1, 'user_id' => 2, 'status' => 'paid', 'total' => 1400, ...$timestamps],
            ['id' => 2, 'user_id' => 3, 'status' => 'paid', 'total' => 6200, ...$timestamps],
        ]);
        $this->db->table('order_items')->insertMany([
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
            return $this->app->console->run($words, new Output($stream, $stream));
        } finally {
            fclose($stream);
        }
    }
}
