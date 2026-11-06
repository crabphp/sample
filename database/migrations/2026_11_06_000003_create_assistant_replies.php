<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('assistant_replies', static function (Table $table): void {
            $table->string('id', 36);
            $table->primary(['id']);
            $table->string('owner', 80);
            $table->text('question');
            $table->text('answer');
            $table->text('products');
            $table->integer('turn');
            $table->integer('expires_at')->index();
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('assistant_replies');
    }
};
