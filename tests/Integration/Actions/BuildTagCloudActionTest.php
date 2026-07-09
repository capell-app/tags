<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Actions\BuildTagCloudAction;
use Capell\Tags\Data\TagCloudItemData;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;

it('limits tag cloud candidates by usage before displaying them alphabetically', function (): void {
    app()->setLocale('en');

    $site = Site::factory()->create();
    $alpha = tagCloudTestTag('Alpha', $site->getKey());
    $bravo = tagCloudTestTag('Bravo', $site->getKey());
    $charlie = tagCloudTestTag('Charlie', $site->getKey());

    tagCloudAttachPages($alpha, 2);
    tagCloudAttachPages($bravo, 5);
    tagCloudAttachPages($charlie, 4);

    $items = BuildTagCloudAction::run(siteId: $site->getKey(), type: 'page', limit: 2, buckets: 5);

    expect($items)->toHaveCount(2)
        ->and($items->map(static fn (TagCloudItemData $item): string => tagCloudTagName($item->tag))->all())->toBe([
            'Bravo',
            'Charlie',
        ])
        ->and($items->map(static fn (TagCloudItemData $item): int => $item->usageCount)->all())->toBe([5, 4])
        ->and($items->map(static fn (TagCloudItemData $item): int => $item->weight)->all())->toBe([5, 4]);
});

it('includes global and matching site tags while excluding other site tags', function (): void {
    app()->setLocale('en');

    $site = Site::factory()->create();
    $otherSite = Site::factory()->create();
    $global = tagCloudTestTag('Global', null);
    $local = tagCloudTestTag('Local', $site->getKey());
    $other = tagCloudTestTag('Other', $otherSite->getKey());

    tagCloudAttachPages($global, 1);
    tagCloudAttachPages($local, 1);
    tagCloudAttachPages($other, 10);

    $items = BuildTagCloudAction::run(siteId: $site->getKey(), type: 'page');

    expect($items->map(static fn (TagCloudItemData $item): string => tagCloudTagName($item->tag))->all())
        ->toBe(['Global', 'Local']);
});

function tagCloudTestTag(string $name, ?int $siteId): Tag
{
    return Tag::factory()->create([
        'name' => ['en' => $name],
        'slug' => ['en' => str($name)->slug()->toString()],
        'site_id' => $siteId,
        'status' => true,
        'type' => 'page',
    ]);
}

function tagCloudAttachPages(Tag $tag, int $count): void
{
    Page::factory()
        ->count($count)
        ->create()
        ->each(static function (Page $page) use ($tag): void {
            Taggable::query()->create([
                'tag_id' => $tag->getKey(),
                'taggable_type' => $page->getMorphClass(),
                'taggable_id' => $page->getKey(),
            ]);
        });
}

function tagCloudTagName(Tag $tag): string
{
    $name = $tag->getTranslation('name', 'en', false);

    return is_string($name) ? $name : '';
}
