<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Actions\ManagePageTagsAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

it('attaches and detaches page tags in the page site scope', function (): void {
    app()->setLocale('en');

    $language = Language::factory()->english()->create();
    $site = Site::factory()->language($language)->withTranslations($language)->create();
    $page = Page::factory()->site($site)->withTranslations($language)->create();

    $oldTag = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => ['en' => 'Old topic'],
        'slug' => ['en' => 'old-topic'],
    ]);

    pageTagsForActionTest($page)->attach($oldTag);

    ManagePageTagsAction::run(
        page: $page,
        tagsToAttach: ['Launch topic'],
        tagsToDetach: ['Old topic'],
    );

    $newTag = Tag::query()
        ->where('type', TagTypeEnum::Page->value)
        ->where('site_id', $site->getKey())
        ->where('name->en', 'Launch topic')
        ->firstOrFail();

    expect(Taggable::query()
        ->where('taggable_type', $page->getMorphClass())
        ->where('taggable_id', $page->getKey())
        ->pluck('tag_id')
        ->all())->toBe([$newTag->getKey()])
        ->and(Tag::query()->whereKey($oldTag->getKey())->exists())->toBeTrue();
});

it('adds missing page tag translations instead of duplicating tags per language', function (): void {
    app()->setLocale('en');

    $english = Language::factory()->english()->create();
    $french = Language::factory()->french()->create();
    $site = Site::factory()->language($english)->withTranslations(collect([$english, $french]))->create();
    $page = Page::factory()->site($site)->withTranslations(collect([$english, $french]))->create();

    ManagePageTagsAction::run(page: $page, tagsToAttach: ['Shared topic']);

    $tag = Tag::query()->where('name->en', 'Shared topic')->firstOrFail();

    expect(Tag::query()->where('type', TagTypeEnum::Page->value)->where('site_id', $site->getKey())->count())->toBe(1)
        ->and($tag->getTranslation('name', 'fr'))->toBe('Shared topic')
        ->and(Taggable::query()
            ->where('taggable_type', $page->getMorphClass())
            ->where('taggable_id', $page->getKey())
            ->where('tag_id', $tag->getKey())
            ->exists())->toBeTrue();
});

/** @return MorphToMany<Tag, Page, MorphPivot, 'pivot'> */
function pageTagsForActionTest(Page $page): MorphToMany
{
    return $page->morphToMany(Tag::class, 'taggable', 'taggables');
}
