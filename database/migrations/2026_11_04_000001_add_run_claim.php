<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

/**
 * Which worker's claim holds a run, which crabphp/framework 0.4.1 checks before a run is renewed or ended.
 */
return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->table('runs', function (Table $table): void {
            $table->string('claim', 32)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('runs', function (Table $table): void {
            $table->dropColumn('claim');
        });
    }
};
