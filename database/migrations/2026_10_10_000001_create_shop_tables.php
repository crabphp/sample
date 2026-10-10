<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('users', function (Table $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            // "member" or "admin".
            $table->string('role', 20);
            $table->timestamps();
        });
        $schema->create('products', function (Table $table) {
            $table->id();
            $table->string('sku', 40)->unique();
            $table->string('name');
            $table->string('category', 40)->index();
            // In cents, so that money is never a fraction.
            $table->integer('price');
            $table->integer('stock');
            $table->timestamps();
        });
        $schema->create('orders', function (Table $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            // "pending", "paid" or "cancelled".
            $table->string('status', 20)->index();
            $table->integer('total');
            $table->timestamps();
        });
        $schema->create('order_items', function (Table $table) {
            $table->id();
            $table->foreignId('order_id')->index();
            $table->foreignId('product_id')->index();
            $table->integer('quantity');
            // What one cost when it was ordered; a product's price may change afterwards.
            $table->integer('price');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('order_items');
        $schema->drop('orders');
        $schema->drop('products');
        $schema->drop('users');
    }
};
