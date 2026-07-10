<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags') || Schema::hasColumn('tags', 'merged_slug_aliases')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->json('merged_slug_aliases')->nullable()->after('slug');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tags') || ! Schema::hasColumn('tags', 'merged_slug_aliases')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->dropColumn('merged_slug_aliases');
        });
    }
};
