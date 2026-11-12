<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('tags', static function (Table $table): void {
            $table->id();
            $table->string('name', 40)->unique();
        });
        $schema->create('taggables', static function (Table $table): void {
            $table->foreignId('tag_id')->references('tags', onDelete: 'cascade');
            $table->string('taggable_type', 40);
            $table->foreignId('taggable_id');
            $table->primary(['taggable_type', 'taggable_id', 'tag_id']);
            $table->index(['tag_id', 'taggable_type']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('taggables');
        $schema->drop('tags');
    }
};
