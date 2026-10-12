<?php

declare(strict_types=1);

namespace Tests;

use App\User;
use App\Users;
use Crab\App;
use Crab\Console\Output;
use Crab\Contracts\Database\Database;
use Crab\Testing\Api;
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

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/crab-sample-' . bin2hex(random_bytes(6));
        mkdir($this->directory . '/mail', recursive: true);
        // The real environment is read before .env, so a test can have a shop of its own.
        foreach ([
            'DB_DSN' => 'sqlite:' . $this->directory . '/shop.sqlite', 'CACHE_STORE' => 'array', 'QUEUE_STORE' => 'database',
            'BROADCAST_CARRIER' => 'local',
            'LOG_PATH' => $this->directory . '/app.log', 'MAIL_PATH' => $this->directory . '/mail', 'MAIL_FAIL_RATE' => '0',
            'REPORTS_PATH' => $this->directory . '/reports', 'WAREHOUSE_URL' => '', 'CLOCKWERK_TOKEN' => 'test-token',
        ] as $name => $value) {
            putenv("{$name}={$value}");
        }
        $this->app = require dirname(__DIR__) . '/app.php';
        // The API fixture represents one serving HTTP worker, including local broadcasts.
        $this->app->container->get(\Crab\Broadcast\Lifecycle::class)->serving = true;
        self::assertSame(0, $this->command('migrate'));
        self::assertSame(0, $this->command('shop:seed'));
        $this->db = $this->app->container->get(Database::class);
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
        return Users::from($row);
    }

    /**
     * Runs an application command and captures its output in memory.
     */
    protected function command(string ...$words): int
    {
        $stream = fopen('php://memory', 'w+');
        return $this->app->console->run($words, new Output($stream, $stream));
    }
}
