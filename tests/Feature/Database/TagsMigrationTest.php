<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

it('loads the tags schema with the type and site lookup index', function (): void {
    expect(Schema::hasTable('tags'))->toBeTrue()
        ->and(Schema::hasTable('taggables'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'slug'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'merged_slug_aliases'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'type'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'status'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'site_id'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'workspace_id'))->toBeTrue()
        ->and(Schema::hasColumn('taggables', 'tag_id'))->toBeTrue()
        ->and(Schema::hasColumn('taggables', 'taggable_type'))->toBeTrue()
        ->and(Schema::hasColumn('taggables', 'taggable_id'))->toBeTrue()
        ->and(Schema::hasIndex('tags', ['type', 'site_id']))->toBeTrue();
});

it('leaves an existing host-owned string status column unchanged', function (): void {
    $statusIndexName = null;

    foreach (Schema::getIndexes('tags') as $index) {
        if (in_array('status', $index['columns'] ?? [], true)) {
            $statusIndexName = $index['name'];

            break;
        }
    }

    if ($statusIndexName !== null) {
        Schema::table('tags', function (Blueprint $table) use ($statusIndexName): void {
            $table->dropIndex($statusIndexName);
        });
    }

    Schema::table('tags', function (Blueprint $table): void {
        $table->dropColumn('status');
    });

    Schema::table('tags', function (Blueprint $table): void {
        $table->string('status')->default('pending')->index();
    });

    $migration = require dirname(__DIR__, 3) . '/database/migrations/2026_08_15_000001_add_status_to_tags_table.php';

    $migration->up();

    expect(Schema::getColumnType('tags', 'status'))->toBeIn(['string', 'varchar']);

    $migration->down();

    expect(Schema::hasColumn('tags', 'status'))->toBeTrue()
        ->and(Schema::getColumnType('tags', 'status'))->toBeIn(['string', 'varchar']);
});
