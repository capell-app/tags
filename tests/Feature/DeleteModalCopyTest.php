<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Tags\Filament\Resources\Tags\Pages\EditTag;
use Capell\Tags\Filament\Resources\Tags\Pages\ListTags;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;
use Livewire\Livewire;

uses(CreatesAdminUser::class);

/**
 * @param  class-string<Component>  $page
 * @param  Closure(): Model  $makeRecord
 */
it('renders translated delete consequences in the real admin surface', function (string $page, Closure $makeRecord, string $mode, string $actionName, string $translation, bool $force): void {
    test()->actingAsAdmin();
    Language::factory()->english()->create();
    $heading = __($translation . '.heading');
    $description = __($translation . '.description');

    expect($heading)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.heading')
        ->and($description)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.description');

    $record = $makeRecord();
    if ($force) {
        $record->delete();
    }

    $parameters = $mode === 'page' ? ['record' => $record->getRouteKey()] : [];
    $component = Livewire::test($page, $parameters)->assertSuccessful();
    if ($mode === 'page') {
        $component->mountAction($actionName);
    } else {
        if ($force) {
            $component->filterTable('trashed', true);
        }

        if ($mode === 'bulk') {
            $component->mountTableBulkAction($actionName, [$record->getKey()]);
        } else {
            $component->mountTableAction($actionName, $record);
        }
    }

    $component->assertMountedActionModalSee($heading)->assertMountedActionModalSee($description);
})->with([
    'EditTag DeleteAction' => [EditTag::class, fn (): Model => Tag::query()->create(['name' => ['en' => 'Delete modal tag'], 'slug' => ['en' => 'delete-modal-tag'], 'type' => 'page']), 'page', 'delete', 'capell-tags::tag.delete_modal', false],
    'TagsTable DeleteAction' => [ListTags::class, fn (): Model => Tag::query()->create(['name' => ['en' => 'Delete modal tag'], 'slug' => ['en' => 'delete-modal-tag'], 'type' => 'page']), 'table', 'delete', 'capell-tags::tag.delete_modal', false],
    'TagsTable DeleteBulkAction' => [ListTags::class, fn (): Model => Tag::query()->create(['name' => ['en' => 'Delete modal tag'], 'slug' => ['en' => 'delete-modal-tag'], 'type' => 'page']), 'bulk', 'delete', 'capell-tags::tag.delete_bulk_modal', false],
]);
