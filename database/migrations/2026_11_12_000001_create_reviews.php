<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('reviews', static function (Table $table): void {
            $table->id();
            $table->foreignId('user_id')->references('users');
            // A polymorphic owner cannot have a foreign key to two tables.
            $table->string('reviewable_type', 40);
            $table->foreignId('reviewable_id');
            $table->integer('rating');
            $table->text('body');
            $table->timestamps();
            $table->index(['reviewable_type', 'reviewable_id']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('reviews');
    }
};
