<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Actions\FindRelatedTaggablesAction;
use Capell\Tags\Data\RelatedTaggableData;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Capell\Tags\Tests\Fixtures\Models\TaggableTestModel;

it('honours registered model, tag site and status, and caller visibility constraints', function (): void {
    $site = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $source = Page::factory()->site($site)->create();
    $visible = Page::factory()->site($site)->create();
    $hidden = Page::factory()->site($site)->create();
    $otherSitePage = Page::factory()->site($otherSite)->create();

    $global = relatedTag('Global', null);
    $local = relatedTag('Local', (int) $site->getKey());
    $disabled = relatedTag('Disabled', (int) $site->getKey(), false);
    $foreign = relatedTag('Foreign', (int) $otherSite->getKey());

    attachRelatedTags($source, [$global, $local, $disabled, $foreign]);
    attachRelatedTags($visible, [$global, $local, $disabled, $foreign]);
    attachRelatedTags($hidden, [$global, $local]);
    attachRelatedTags($otherSitePage, [$global]);

    $results = FindRelatedTaggablesAction::run(
        record: $source,
        relatedQuery: Page::query()->whereKey($visible->getKey()),
        tagType: 'page',
        limit: 1,
    );

    expect($results)->toHaveCount(1)
        ->and($results->first())->toBeInstanceOf(RelatedTaggableData::class)
        ->and($results->first()?->record->is($visible))->toBeTrue()
        ->and($results->first()?->sharedTagCount)->toBe(2);
});

it('rejects related model types that were not registered with tags', function (): void {
    $source = Page::factory()->create();

    expect(fn (): mixed => FindRelatedTaggablesAction::run(
        record: $source,
        relatedQuery: TaggableTestModel::query(),
    ))->toThrow(InvalidArgumentException::class, 'is not registered');
});

function relatedTag(string $name, ?int $siteId, bool $enabled = true): Tag
{
    return Tag::factory()->create([
        'name' => ['en' => $name],
        'slug' => ['en' => str($name)->slug()->toString()],
        'site_id' => $siteId,
        'status' => $enabled,
        'type' => 'page',
    ]);
}

/** @param list<Tag> $tags */
function attachRelatedTags(Page $page, array $tags): void
{
    foreach ($tags as $tag) {
        Taggable::query()->create([
            'tag_id' => $tag->getKey(),
            'taggable_type' => $page->getMorphClass(),
            'taggable_id' => $page->getKey(),
        ]);
    }
}
