<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Site;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Filament\Resources\Tags\Pages\CreateTag;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\CreatesAdminUser;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)
    ->group('tag');

beforeEach(function (): void {
    Language::factory()->default()->create();

    test()->actingAsAdmin();
});

test('required fields are required', function (): void {
    livewire(CreateTag::class)
        ->assertSuccessful()
        ->call('create')
        ->assertHasFormErrors([
            'name' => 'required',
            'slug' => 'required',
        ]);
});

it('can create', function (): void {
    $newData = Tag::factory()->make();

    livewire(CreateTag::class)
        ->assertSuccessful()
        ->set('data.translations', [])
        ->fillForm([
            'name' => $newData->name,
            'slug' => $newData->slug,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    assertDatabaseHas(Tag::class, [
        'name' => json_encode(['en' => $newData->name]),
        'slug' => json_encode(['en' => $newData->slug]),
    ]);
});

it('allows duplicate slugs across different sites', function (): void {
    $firstSite = Site::factory()->withTranslations()->create();
    $secondSite = Site::factory()->withTranslations()->create();

    Tag::factory()->create([
        'slug' => ['en' => 'site-topic'],
        'site_id' => $firstSite->getKey(),
        'type' => TagTypeEnum::Page->value,
    ]);

    livewire(CreateTag::class)
        ->assertSuccessful()
        ->set('data.translations', [])
        ->fillForm([
            'name' => 'Site Topic',
            'slug' => 'site-topic',
            'type' => TagTypeEnum::Page->value,
            'site_id' => $secondSite->getKey(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});
