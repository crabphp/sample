<?php

declare(strict_types=1);

namespace App\Actions;

use Crab\Contracts\Cache\Cache;
use Crab\Contracts\Database\Database;
use Crab\Http\HttpException;

/**
 * Whether this server can take traffic: it reaches the database and the
 * cache, which every page needs. A readiness probe points here and a
 * liveness probe at /up, so an outage takes the shop out of rotation
 * without restarting it. The warehouse is left out: when it is down,
 * orders still go through and stock is synced later.
 */
final readonly class Ready
{
    public function __construct(private Database $db, private Cache $cache)
    {
    }

    /**
     * @return array{status: string}
     */
    public function handle(): array
    {
        try {
            $this->db->select('SELECT 1');
            $this->cache->get('ready');
        } catch (\Throwable $error) {
            // Logged with its cause; the client is told no more than this.
            throw new HttpException(503, 'Not ready.', previous: $error);
        }

        return ['status' => 'ready'];
    }
}
