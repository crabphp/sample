<?php

declare(strict_types=1);

namespace Tests;

use App\Actions\ShipOrder;
use Crab\Contracts\Database\Connection;
use Crab\Contracts\Database\Database;
use Crab\Contracts\Database\Query;
use Crab\Database\Query as QueryBuilder;
use Crab\Engine\EventLoop;
use Crab\Engine\EventLoop\RevoltEventLoop;

/**
 * Holds an already-read snapshot while a committed update reaches the
 * socket, as an asynchronous database response can do.
 */
final class LiveOrderRaceTest extends ShopTestCase
{
    public function testAnOlderReadySnapshotCannotReplaceAnUpdateAlreadyReceived(): void
    {
        EventLoop::install(RevoltEventLoop::create());
        $owner = $this->user('ada@example.com');
        $id = (int) $this->db->table('orders')->where('user_id', $owner->id)->where('status', 'paid')->value('id');
        $db = new SnapshotDatabase($this->db);
        $db->afterFirst = function (?array $row) use ($db, $id): void {
            if ($row === null || !array_key_exists('status', $row) || array_key_exists('id', $row)) {
                return;
            }
            $db->afterFirst = null;
            $this->app->container->get(ShipOrder::class)->handle($id);
            $loop = EventLoop::current();
            $wait = $loop->getSuspension();
            $loop->delay(0.01, $wait->resume(...));
            $wait->suspend();
        };
        $this->app->container->instance(Database::class, $db);
        $socket = $this->api->actingAs($owner)->websocket("/orders/{$id}/live");
        $messages = [$socket->receive(), $socket->receive()];
        self::assertSame(['status', 'ready'], array_column($messages, 'type'));
        self::assertSame(['shipped', 'paid'], array_column($messages, 'status'));

        $order = ['version' => 0];
        foreach ([...$messages, $messages[0]] as $message) {
            self::assertArrayHasKey('version', $message);
            // The browser uses this same rule, including for duplicate updates.
            if ($message['version'] <= $order['version']) {
                continue;
            }
            $order = $message;
        }
        self::assertGreaterThan($messages[1]['version'], $messages[0]['version']);
        self::assertSame('shipped', $order['status']);
        self::assertSame((int) $this->db->table('orders')->where('id', $id)->value('version'), $order['version']);
    }
}

/**
 * Leaves the real database intact and delays only delivery of a first()
 * result, so the race crosses the real action and broadcast path.
 */
final class SnapshotDatabase implements Database
{
    public ?\Closure $afterFirst = null;

    public function __construct(private Database $db) {}
    public function connection(string $name): Database { return $this; }
    public function owns(Connection $connection): bool { return $this->db->owns($connection); }
    public function acquire(): Connection { return $this->db->acquire(); }
    public function release(Connection $connection): void { $this->db->release($connection); }
    public function run(\Closure $callback): mixed { return $this->db->run($callback); }
    public function table(string $table): Query { return new QueryBuilder($this, $table); }
    public function driver(): string { return $this->db->driver(); }
    public function select(string $sql, array $bindings = []): array { return $this->db->select($sql, $bindings); }
    public function first(string $sql, array $bindings = []): ?array
    {
        $row = $this->db->first($sql, $bindings);
        if ($this->afterFirst !== null) {
            ($this->afterFirst)($row);
        }
        return $row;
    }
    public function insert(string $sql, array $bindings = []): int|string { return $this->db->insert($sql, $bindings); }
    public function execute(string $sql, array $bindings = []): int { return $this->db->execute($sql, $bindings); }
    public function transaction(\Closure $callback): mixed { return $this->db->transaction($callback); }
    public function afterCommit(\Closure $callback): void { $this->db->afterCommit($callback); }
}
