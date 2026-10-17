<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\Facades\FilamentShield;
use BezhanSalleh\FilamentShield\Support\Utils;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Filament\Resources\Tags\Pages\EditTag;
use Capell\Tags\Filament\Resources\Tags\RelationManagers\PagesRelationManager;
use Capell\Tags\Models\Tag;
use Capell\Tests\Fixtures\Models\User;

use function Pest\Livewire\livewire;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('can list pages for a tag', function (): void {
    config()->set('permission.teams', true);
    $registrar = resolve(PermissionRegistrar::class);
    $registrar->teams = true;

    $site = Site::factory()->create();
    $user = User::factory()->create();
    $role = Role::findOrCreate('tags-site-viewer', 'web');
    $permissions = Utils::getConfig()->permissions;
    $permission = FilamentShield::defaultPermissionKeyBuilder(
        affix: 'view_any',
        separator: $permissions->separator,
        subject: 'Page',
        case: $permissions->case,
    );
    $role->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    $user->assignRoleForSite($site, $role);

    $registrar->setPermissionsTeamId($site->id);
    $this->actingAs($user);

    $tag = Tag::factory()
        ->site($site)
        ->has(Page::factory()->site($site)->withTranslations()->count(5), 'pages')
        ->create();

    $page = $tag->pages->first();

    livewire(PagesRelationManager::class, [
        'ownerRecord' => $tag,
        'pageClass' => EditTag::class,
    ])
        ->assertSuccessful()
        ->assertCountTableRecords(5)
        ->assertCanSeeTableRecords($tag->pages)
        ->assertTableColumnStateSet('name', $page->name, record: $page);
});

it('filters related pages by the visible page name', function (): void {
    config()->set('permission.teams', true);
    $registrar = resolve(PermissionRegistrar::class);
    $registrar->teams = true;
    $site = Site::factory()->create();
    $user = User::factory()->create();
    $user->assignRoleForSite($site, Role::findOrCreate('super_admin', 'web'));
    $registrar->setPermissionsTeamId($site->id);
    $this->actingAs($user);
    $tag = Tag::factory()->site($site)->create();
    $matchingPage = Page::factory()->site($site)->withTranslations()->create(['name' => 'Matching Relation Page']);
    $otherPage = Page::factory()->site($site)->withTranslations()->create(['name' => 'Other Relation Page']);
    $unrelatedPage = Page::factory()->site($site)->withTranslations()->create(['name' => 'Matching Unrelated Page']);
    $tag->pages()->attach([$matchingPage->getKey(), $otherPage->getKey()]);

    livewire(PagesRelationManager::class, [
        'ownerRecord' => $tag, 'pageClass' => EditTag::class,
    ])->assertSuccessful()->searchTable('Matching')->assertSuccessful()
        ->assertCanSeeTableRecords([$matchingPage])
        ->assertCanNotSeeTableRecords([$otherPage, $unrelatedPage])
        ->assertCountTableRecords(1);
});
