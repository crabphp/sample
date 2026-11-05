<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

/**
 * Which worker holds a run, fenced since framework 0.5.0 and now checked by crabphp/runs.
 */
return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->table('runs', static function (Table $table): void {
            $table->string('claim', 32)->nullable();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('runs', static function (Table $table): void {
            $table->dropColumn('claim');
        });
    }
};
