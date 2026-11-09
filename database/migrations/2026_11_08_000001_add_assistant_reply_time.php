<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->table('assistant_replies', static function (Table $table): void {
            $table->integer('created_at')->nullable()->index();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->table('assistant_replies', static function (Table $table): void {
            $table->dropColumn('created_at');
        });
    }
};
