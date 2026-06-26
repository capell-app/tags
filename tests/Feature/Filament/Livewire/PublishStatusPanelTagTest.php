<?php

declare(strict_types=1);

use Capell\Admin\Filament\Livewire\PublishStatusPanel;
use Capell\Core\Models\Language;
use Capell\Tags\Filament\Resources\Tags\Pages\CreateTag;
use Capell\Tags\Filament\Resources\Tags\Pages\EditTag;
use Capell\Tags\Models\Tag;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Livewire\Livewire;

use function Pest\Livewire\livewire;

uses(CreatesAdminUser::class)->group('page', 'tag');

beforeEach(function (): void {
    Language::factory()->default()->create();

    test()->actingAsAdmin();
});

it('renders a status-only publish panel for a Tag', function (): void {
    $tag = Tag::factory()->create(['status' => true]);

    Livewire::test(PublishStatusPanel::class, [
        'recordClass' => Tag::class,
        'recordId' => $tag->getKey(),
    ])
        ->assertOk()
        ->assertActionVisible('toggleStatus')
        ->assertActionHidden('publishNow')
        ->assertActionHidden('unpublish');
});

it('toggles a Tag Active/Inactive from the panel', function (): void {
    $tag = Tag::factory()->create(['status' => true]);

    Livewire::test(PublishStatusPanel::class, [
        'recordClass' => Tag::class,
        'recordId' => $tag->getKey(),
    ])->callAction('toggleStatus');

    expect($tag->fresh()?->isEnabled())->toBeFalse();
});

it('mounts the publish panel on the Tag edit page', function (): void {
    $tag = Tag::factory()->create(['status' => true]);

    livewire(EditTag::class, [
        'record' => $tag->getRouteKey(),
    ])
        ->assertSuccessful()
        ->assertSeeLivewire(PublishStatusPanel::class);
});

it('keeps the inline status toggle and omits the panel on the Tag create page', function (): void {
    livewire(CreateTag::class)
        ->assertSuccessful()
        ->assertFormFieldExists('status')
        ->assertDontSeeLivewire(PublishStatusPanel::class);
});
