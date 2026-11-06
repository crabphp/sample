<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('carts', static function (Table $table): void {
            $table->string('id', 80);
            $table->primary(['id']);
            $table->text('lines');
            $table->string('checkout_id', 36);
            $table->integer('expires_at')->index();
        });
        $schema->create('cart_operations', static function (Table $table): void {
            $table->string('id', 64);
            $table->primary(['id']);
            $table->integer('expires_at')->index();
        });
        $schema->create('cart_checkouts', static function (Table $table): void {
            $table->string('id', 36);
            $table->primary(['id']);
            $table->foreignId('user_id')->index();
            $table->foreignId('order_id');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('cart_checkouts');
        $schema->drop('cart_operations');
        $schema->drop('carts');
    }
};
