<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('auth_tokens', function (Table $table) {
            $table->id();
            // The id of the user the token belongs to, as text so that any kind of id fits.
            $table->string('owner_id', 64)->index();
            $table->string('name')->default('');
            // The SHA-256 of the token; the token itself is never stored.
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('auth_tokens');
    }
};
