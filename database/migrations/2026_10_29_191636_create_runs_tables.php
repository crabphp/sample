<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        $schema->create('runs', function (Table $table) {
            $table->id();
            // The run's class, and the arguments of its handle() as JSON.
            $table->string('run');
            $table->text('data');
            $table->string('status', 16);
            $table->text('result')->nullable();
            $table->text('error')->nullable();
            $table->integer('attempts')->default(0);
            // Unix timestamps: until when a worker holds it, when its sleep ends.
            $table->bigInteger('lease_until')->nullable();
            $table->bigInteger('wake_at')->nullable();
            $table->bigInteger('created_at');
            $table->bigInteger('updated_at');
            $table->index(['status', 'id']);
        });
        $schema->create('run_steps', function (Table $table) {
            $table->id();
            $table->bigInteger('run_id');
            // A step's, a sleep's or a signal's name, unique in its run.
            $table->string('name', 191);
            $table->string('kind', 8);
            $table->text('result')->nullable();
            $table->bigInteger('wake_at')->nullable();
            $table->bigInteger('created_at');
            $table->unique(['run_id', 'name']);
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('run_steps');
        $schema->drop('runs');
    }
};
