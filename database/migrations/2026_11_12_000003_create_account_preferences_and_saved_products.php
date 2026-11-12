<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('account_preferences', static function (Table $table): void {
            $table->foreignId('user_id')->references('users', onDelete: 'cascade');
            $table->primary(['user_id']);
            $table->boolean('newsletter')->default(false);
            $table->json('categories');
            $table->dateTime('updated_at')->nullable();
        });
        $schema->create('saved_products', static function (Table $table): void {
            $table->foreignId('user_id')->references('users', onDelete: 'cascade');
            $table->foreignId('product_id')->references('products', onDelete: 'cascade');
            $table->primary(['user_id', 'product_id']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('saved_products');
        $schema->drop('account_preferences');
    }
};
