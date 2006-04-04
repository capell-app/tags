<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Core\Models\Language;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Filament\Resources\Tags\Pages\ListTags;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Eloquent\ModelNotFoundException;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;

uses(CreatesAdminUser::class)
    ->group('tag');

beforeEach(function (): void {
    Language::factory()->default()->create();

    test()->actingAsAdmin();
});

test('can list tags', function (): void {
    $tags = Tag::factory()->count(5)->create();

    livewire(ListTags::class)
        ->assertSuccessful()
        ->assertCountTableRecords(5)
        ->assertCanSeeTableRecords($tags);
});

test('can search tags', function (): void {
    $tags = Tag::factory()
        ->sequence(fn (Sequence $sequence): array => ['name' => sprintf('Language(%d)', $sequence->index)])
        ->count(3)
        ->create();

    $name = $tags->random()->name;

    livewire(ListTags::class)
        ->assertSuccessful()
        ->assertCountTableRecords(3)
        ->searchTable($name)
        ->assertCountTableRecords(1)
        ->assertCanSeeTableRecords($tags->where('name', $name))
        ->assertCanNotSeeTableRecords($tags->where('name', '!=', $name));
});

test('searches tags in the active table locale', function (): void {
    Language::factory()->german()->create();

    $translatedTag = Tag::factory()->create([
        'name' => [
            'en' => 'Architecture',
            'de' => 'Architektur',
        ],
        'slug' => [
            'en' => 'architecture',
            'de' => 'architektur',
        ],
    ]);

    $englishOnlyTag = Tag::factory()->create([
        'name' => ['en' => 'Translation'],
        'slug' => ['en' => 'translation'],
    ]);

    livewire(ListTags::class)
        ->assertSuccessful()
        ->set('activeLocale', 'de')
        ->searchTable('Architektur')
        ->assertCanSeeTableRecords([$translatedTag])
        ->assertCanNotSeeTableRecords([$englishOnlyTag]);
});

test('can sort tags', function (): void {
    $tags = Tag::factory()->count(5)->create();

    livewire(ListTags::class)
        ->assertSuccessful()
        ->assertCountTableRecords(5)
        ->sortTable('name')
        ->assertCanSeeTableRecords($tags->sortBy('name'), inOrder: true);
});

test('can replicate tag', function (): void {
    $tag = Tag::factory()->create();

    $name = $tag->name . ' (copy)';
    $slug = str($name)->slug();

    livewire(ListTags::class)
        ->assertSuccessful()
        ->assertCountTableRecords(1)
        ->callAction(
            TestAction::make(ReplicateAction::class)->table($tag),
            data: [
                'name' => $name,
                'slug' => $slug,
            ],
        )
        ->assertHasNoFormErrors()
        ->assertCountTableRecords(2);

    assertDatabaseHas('tags', [
        'name' => json_encode(['en' => $name]),
        'slug' => json_encode(['en' => $slug]),
    ]);
});

test('can delete tag', function (): void {
    $tag = Tag::factory()->create();

    livewire(ListTags::class)
        ->assertSuccessful()
        ->assertCountTableRecords(1)
        ->callAction(TestAction::make(DeleteAction::class)->table($tag))
        ->assertHasNoFormErrors()
        ->assertCountTableRecords(0);

    expect(fn () => $tag->refresh())->toThrow(ModelNotFoundException::class);
});

test('can group delete tags', function (): void {
    $tags = Tag::factory()->count(5)->create();

    livewire(ListTags::class)
        ->assertSuccessful()
        ->selectTableRecords($tags)
        ->callAction(TestAction::make(DeleteBulkAction::class)->table()->bulk())
        ->assertHasNoFormErrors();

    foreach ($tags as $tag) {
        assertModelMissing($tag);
    }
});

test('explicitly authorizes the destructive tag merge action', function (): void {
    $component = livewire(ListTags::class)->assertSuccessful()->instance();
    throw_unless($component instanceof ListTags, RuntimeException::class, 'Expected the ListTags component.');
    $action = $component->getTable()->getBulkAction('mergeTags');

    expect($action)->not->toBeNull()
        ->and($action?->isAuthorized())->toBeTrue();

    $permissions = Utils::getConfig()->permissions;
    $permission = FilamentShield::defaultPermissionKeyBuilder(
        affix: 'view_any',
        separator: $permissions->separator,
        subject: 'Tag',
        case: $permissions->case,
    );
    Permission::findOrCreate($permission);
    $user = test()->createUserWithPermission($permission);
    test()->actingAs($user);

    $component = livewire(ListTags::class)->assertSuccessful()->instance();
    throw_unless($component instanceof ListTags, RuntimeException::class, 'Expected the ListTags component.');
    $action = $component->getTable()->getBulkAction('mergeTags');

    expect($action?->isAuthorized())->toBeFalse();
});

it('requires a server review before applying a bulk merge', function (): void {
    $tags = Tag::factory()->type(TagTypeEnum::Page)->count(2)->create();
    livewire(ListTags::class)
        ->selectTableRecords($tags)
        ->callAction(TestAction::make('mergeTags')->table()->bulk(), data: ['target_tag_id' => $tags[0]->id])
        ->assertHasErrors();
    expect(Tag::query()->whereKey($tags->modelKeys())->count())->toBe(2);
});

it('opens an actor scoped usage summary from the total column', function (): void {
    $tag = Tag::factory()->create();
    $component = livewire(ListTags::class)->mountAction(TestAction::make('tagUsage')->table($tag));
    $instance = $component->instance();
    throw_unless($instance instanceof ListTags, RuntimeException::class, 'Expected tag listing');
    $content = $instance->getMountedAction()?->getModalContent();
    throw_unless($content instanceof View, RuntimeException::class, 'Expected usage view');
    expect($content->render())->toContain(__('capell-tags::generic.usage_empty'));
});

it('previews then applies the selected merge and clears the review', function (): void {
    $tags = Tag::factory()->type(TagTypeEnum::Page)->count(2)->create();
    $component = livewire(ListTags::class)
        ->selectTableRecords($tags)
        ->mountAction(TestAction::make('mergeTags')->table()->bulk())
        ->fillForm(['target_tag_id' => $tags[0]->id])
        ->goToNextWizardStep()
        ->assertHasNoErrors();
    expect($component->instance()->getSchema($component->instance()->getMountedActionSchemaName() ?? throw new RuntimeException('Expected action schema'))?->toHtml())->toContain(__('capell-tags::generic.merge_aliases'));
    expect($component->get('mergeReviewFingerprint'))->toBeString();
    expect(Tag::query()->whereKey($tags->modelKeys())->count())->toBe(2);
    $component->callMountedAction()->assertHasNoErrors()->assertSet('mergeReviewFingerprint', null);
    expect(Tag::query()->whereKey($tags->modelKeys())->count())->toBe(1);
});

it('invalidates a mounted review when its source changes before apply', function (): void {
    $tags = Tag::factory()->type(TagTypeEnum::Page)->count(2)->create();
    $component = livewire(ListTags::class)
        ->selectTableRecords($tags)
        ->mountAction(TestAction::make('mergeTags')->table()->bulk())
        ->fillForm(['target_tag_id' => $tags[0]->id])
        ->goToNextWizardStep()->assertHasNoErrors();
    $tags[1]->forceFill(['featured' => ! $tags[1]->featured])->save();
    $component->callMountedAction()->assertHasErrors();
    expect(Tag::query()->whereKey($tags->modelKeys())->count())->toBe(2);
});
