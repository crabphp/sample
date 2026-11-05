<?php

declare(strict_types=1);

namespace Tests;

use Crab\Database\Schema;

final class MoneyMigrationTest extends ShopTestCase
{
    public function testWideningKeepsExistingTotalsAndRollbackCannotTruncateThem(): void
    {
        $migration = require dirname(__DIR__) . '/database/migrations/2026_11_06_000001_widen_order_total.php';
        $schema = new Schema($this->db);
        // Start from the old schema, as an existing shop does, then widen it again.
        $migration->down($schema);
        $migration->up($schema);
        self::assertSame(1400, $this->db->table('orders')->where('id', 1)->value('total'));
        $this->db->table('orders')->where('id', 1)->update(['total' => 40_000_000_000]);

        $error = null;
        try {
            $migration->down($schema);
        } catch (\LogicException $caught) {
            $error = $caught;
        }

        self::assertSame(40_000_000_000, $this->db->table('orders')->where('id', 1)->value('total'));
        if ($this->db->driver() === 'mysql') {
            self::assertInstanceOf(\LogicException::class, $error);
        } else {
            self::assertNull($error, 'SQLite needs no narrowing operation.');
        }
    }
}
