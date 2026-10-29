<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

/**
 * The run that follows an order up after it is placed, for shipping it to signal.
 */
return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->table('orders', function (Table $table): void {
            $table->bigInteger('follow_up_run')->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('orders', function (Table $table): void {
            $table->dropColumn('follow_up_run');
        });
    }
};
