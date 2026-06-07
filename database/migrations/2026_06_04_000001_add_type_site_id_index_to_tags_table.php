<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const string INDEX_NAME = 'tags_type_site_id_index';

    public function up(): void
    {
        if (
            ! Schema::hasTable('tags')
            || ! Schema::hasColumn('tags', 'type')
            || ! Schema::hasColumn('tags', 'site_id')
            || Schema::hasIndex('tags', ['type', 'site_id'])
            || Schema::hasIndex('tags', self::INDEX_NAME)
        ) {
            return;
        }

        $indexName = self::INDEX_NAME;

        Schema::table('tags', function (Blueprint $table) use ($indexName): void {
            $table->index(['type', 'site_id'], $indexName);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tags') || ! Schema::hasIndex('tags', self::INDEX_NAME)) {
            return;
        }

        $indexName = self::INDEX_NAME;

        Schema::table('tags', function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }
};
