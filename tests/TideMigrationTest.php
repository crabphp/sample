<?php

declare(strict_types=1);

namespace Tests;

use Crab\Database\Schema;

final class TideMigrationTest extends ShopTestCase
{
    public function testRelationMigrationsRollBackAndReapplyWithoutTouchingTheShop(): void
    {
        $schema = new Schema($this->db);
        $migrations = array_map(
            static fn(string $file) => require $file,
            glob(dirname(__DIR__) . '/database/migrations/2026_11_12_*.php'),
        );
        foreach (array_reverse($migrations) as $migration) {
            $migration->down($schema);
        }
        foreach (['reviews', 'tags', 'taggables', 'account_preferences', 'saved_products'] as $table) {
            self::assertFalse($schema->hasTable($table));
        }
        self::assertSame(6, $this->db->table('products')->count());
        self::assertSame(2, $this->db->table('orders')->count());
        foreach ($migrations as $migration) {
            $migration->up($schema);
        }
        foreach (['reviews', 'tags', 'taggables', 'account_preferences', 'saved_products'] as $table) {
            self::assertTrue($schema->hasTable($table));
        }
        $ada = $this->api->actingAs($this->user('ada@example.com'));
        $ada->post('/api/products/1/reviews', ['rating' => 5, 'body' => 'Still works'])->assertStatus(201);
        $ada->put('/api/me/saved-products/1')->assertStatus(204);
        $ada->put('/api/me/preferences', ['newsletter' => true, 'categories' => ['desk']])->assertStatus(200);
    }
}
