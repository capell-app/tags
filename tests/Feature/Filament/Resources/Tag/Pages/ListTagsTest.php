<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Filament\Resources\Tags\Pages\ListTags;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertModelMissing;
use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

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

test('renders site tabs with counts and filters tags for a global admin', function (): void {
    $firstSite = Site::factory()->create(['name' => 'First tag site']);
    $secondSite = Site::factory()->create(['name' => 'Second tag site']);
    $firstSiteTags = Tag::factory()->count(2)->site($firstSite)->create();
    $secondSiteTags = Tag::factory()->count(3)->site($secondSite)->create();

    $component = livewire(ListTags::class)
        ->assertSuccessful()
        ->assertSee($firstSite->name)
        ->assertSee($secondSite->name);
    $instance = $component->instance();
    throw_unless($instance instanceof ListTags, RuntimeException::class, 'Expected the ListTags component.');

    $tabs = $instance->getCachedTabs();

    expect(array_keys($tabs))->toEqualCanonicalizing(['all', 'none', $firstSite->getKey(), $secondSite->getKey()])
        ->and($tabs[$firstSite->getKey()]->getBadge())->toBe('2')
        ->and($tabs[$secondSite->getKey()]->getBadge())->toBe('3');

    $component
        ->set('activeTab', (string) $firstSite->getKey())
        ->assertCountTableRecords(2)
        ->assertCanSeeTableRecords($firstSiteTags)
        ->assertCanNotSeeTableRecords($secondSiteTags);
});

test('limits site tabs and records to the assigned site for a scoped admin', function (): void {
    $assignedSite = Site::factory()->create(['name' => 'Assigned tag site']);
    $otherSite = Site::factory()->create(['name' => 'Other tag site']);
    $assignedTags = Tag::factory()->count(2)->site($assignedSite)->create();
    $otherTags = Tag::factory()->count(3)->site($otherSite)->create();

    $permissions = Utils::getConfig()->permissions;
    $permission = FilamentShield::defaultPermissionKeyBuilder(
        affix: 'view_any',
        separator: $permissions->separator,
        subject: 'Tag',
        case: $permissions->case,
    );
    Permission::findOrCreate($permission);
    $user = capell_test_instance(test()->createUserWithPermission($permission), User::class);
    $role = capell_test_instance(Role::findOrCreate('tags-site-viewer', 'web'), Role::class);
    $user->assignRoleForSite($assignedSite, $role);
    DB::table('model_has_roles')
        ->where('role_id', $role->getKey())
        ->where('model_type', $user->getMorphClass())
        ->where('model_id', $user->getKey())
        ->update(['team_id' => $assignedSite->getKey()]);
    test()->actingAs($user);

    $component = livewire(ListTags::class)
        ->assertSuccessful()
        ->assertSee($assignedSite->name)
        ->assertCountTableRecords(2)
        ->assertCanSeeTableRecords($assignedTags)
        ->assertCanNotSeeTableRecords($otherTags);
    $instance = $component->instance();
    throw_unless($instance instanceof ListTags, RuntimeException::class, 'Expected the ListTags component.');

    expect(array_keys($instance->getCachedTabs()))
        ->toEqualCanonicalizing(['all', 'none', $assignedSite->getKey()])
        ->not->toContain($otherSite->getKey());
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

test('uses filter-safe empty-state copy when the status filter excludes an existing tag', function (): void {
    $tag = Tag::factory()->create(['status' => true]);

    $component = livewire(ListTags::class)
        ->assertSuccessful()
        ->assertCountTableRecords(1)
        ->filterTable('status', false)
        ->assertCountTableRecords(0);
    $instance = $component->instance();
    throw_unless($instance instanceof ListTags, RuntimeException::class, 'Expected the ListTags component.');

    expect($tag->exists)->toBeTrue()
        ->and($instance->getTable()->getEmptyStateHeading())->toBe('No tags found')
        ->and($instance->getTable()->getEmptyStateDescription())
        ->toBe('No tags match the current selection. Try adjusting your search or filters.');
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
