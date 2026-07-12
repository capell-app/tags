<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schema;

it('loads the tags schema with the type and site lookup index', function (): void {
    expect(Schema::hasTable('tags'))->toBeTrue()
        ->and(Schema::hasTable('taggables'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'name'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'slug'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'merged_slug_aliases'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'type'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'site_id'))->toBeTrue()
        ->and(Schema::hasColumn('tags', 'workspace_id'))->toBeTrue()
        ->and(Schema::hasColumn('taggables', 'tag_id'))->toBeTrue()
        ->and(Schema::hasColumn('taggables', 'taggable_type'))->toBeTrue()
        ->and(Schema::hasColumn('taggables', 'taggable_id'))->toBeTrue()
        ->and(Schema::hasIndex('tags', ['type', 'site_id']))->toBeTrue();
});
