<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tags') || Schema::hasColumn('tags', 'status')) {
            return;
        }

        Schema::table('tags', function (Blueprint $table): void {
            $table->boolean('status')->index()->default(1);
        });
    }

    public function down(): void
    {
        // The status column may be owned by a consuming application with a different type.
        // Leave it intact so package rollback cannot remove host-owned state.
    }
};
