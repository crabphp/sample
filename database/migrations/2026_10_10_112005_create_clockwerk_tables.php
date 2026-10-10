<?php

declare(strict_types=1);

use Crab\Contracts\Database\Migration;
use Crab\Contracts\Database\Schema;
use Crab\Contracts\Database\Table;

return new class implements Migration {
    public function up(Schema $schema): void
    {
        // What each worker counted, by minute: requests by route, jobs by class, tasks by name.
        $schema->create('clockwerk_metrics', function (Table $table) {
            $table->id();
            $table->string('worker', 60);
            $table->string('kind', 16);
            $table->string('name', 160);
            // Tells names apart exactly, where the database would take "/Foo" and "/foo" for one.
            $table->string('name_hash', 40);
            // The Unix timestamp of the start of the minute.
            $table->bigInteger('bucket');
            $table->integer('runs');
            $table->float('total_ms');
            $table->float('max_ms');
            $table->integer('errors');
            $table->unique(['worker', 'kind', 'name_hash', 'bucket']);
            $table->index(['kind', 'bucket']);
        });
        // What is read one by one: exceptions, with where they came from.
        $schema->create('clockwerk_events', function (Table $table) {
            $table->id();
            $table->string('kind', 16);
            $table->bigInteger('at');
            $table->string('title');
            $table->text('detail');
            $table->index(['kind', 'at']);
        });
        // The workers alive, each rewritten every few seconds by itself.
        $schema->create('clockwerk_workers', function (Table $table) {
            $table->string('id', 60)->unique();
            $table->string('group_name', 60);
            $table->integer('pid');
            $table->string('host', 120);
            $table->bigInteger('started_at');
            $table->bigInteger('seen_at')->index();
            $table->bigInteger('memory');
            $table->bigInteger('handled');
        });
    }

    public function down(Schema $schema): void
    {
        $schema->drop('clockwerk_workers');
        $schema->drop('clockwerk_events');
        $schema->drop('clockwerk_metrics');
    }
};
