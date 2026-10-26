<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

/**
 * What differs from one kind of product to another, as JSON:
 * {"colour": "blue", "dishwasherSafe": true}. The catalogue filters on it
 * by path, details->colour, without a column for each.
 */
return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->table('products', function (Table $table): void {
            $table->json('details')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('products', function (Table $table): void {
            $table->dropColumn('details');
        });
    }
};
