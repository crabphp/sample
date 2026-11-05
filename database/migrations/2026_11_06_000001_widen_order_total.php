<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;

/**
 * Twenty lines of twenty items at the allowed price can total forty
 * billion cents. SQLite already stores 64-bit integers; MySQL needs BIGINT.
 */
return new class implements Migration {
    public function up(Schema $schema): void
    {
        if ($schema->connection()->driver() === 'mysql') {
            $schema->connection()->execute('ALTER TABLE orders MODIFY total BIGINT NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $db = $schema->connection();
        if ($db->driver() !== 'mysql') {
            return;
        }
        if ($db->table('orders')->where('total', '>', 2_147_483_647)->orWhere('total', '<', -2_147_483_648)->exists()) {
            throw new \LogicException('Order totals exceed a 32-bit integer; keep the wider total column.');
        }
        $db->execute('ALTER TABLE orders MODIFY total INT NOT NULL');
    }
};
