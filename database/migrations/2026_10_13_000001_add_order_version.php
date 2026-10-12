<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

/**
 * Orders snapshots and updates even when several changes share a second.
 */
return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->table('orders', function (Table $table): void {
            $table->bigInteger('version')->default(1);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('orders', function (Table $table): void {
            $table->dropColumn('version');
        });
    }
};
