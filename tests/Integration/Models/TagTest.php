<?php

declare(strict_types=1);

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\PageUrl;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Capell\Tags\Tests\Fixtures\Models\TaggablePage;
use Illuminate\Database\Eloquent\Relations\Relation;

it('belongs to a site', function (): void {
    $site = Site::factory()->create();
    $tag = Tag::factory()->create(['site_id' => $site->id]);

    expect($tag->site)->toBeInstanceOf(Site::class)
        ->and($tag->site->id)->toBe($site->id);
});

it('has featured and status attributes', function (): void {
    $tag = Tag::factory()->create(['featured' => true, 'status' => false]);

    expect($tag->featured)->toBeTrue()
        ->and($tag->status)->toBeFalse();
});

it('can be attached to pages', function (): void {
    $tag = Tag::factory()->create();
    $page = Page::factory()->create();

    $tag->pages()->attach($page);

    expect($tag->pages)->toHaveCount(1)
        ->and($tag->pages->first()->id)->toBe($page->id);
});

it('builds a fallback tag URL when the tag page URL is not loaded', function (): void {
    $language = Language::factory()->english()->create();
    $tag = Tag::factory()->create([
        'slug' => ['en' => 'launch-news'],
    ]);
    $tagPage = Page::factory()->create();
    $slug = $tag->getTranslation('slug', 'en');

    expect($tag->getUrl($tagPage, $language))->toBe('/' . $slug);
});

it('builds tag URLs from loaded page URLs and wildcard paths', function (): void {
    $language = Language::factory()->english()->create();
    $site = Site::factory()
        ->language($language)
        ->withTranslations(
            languages: $language,
            siteDomainData: [
                'default' => true,
                'domain' => 'tags.example.test',
                'path' => null,
                'scheme' => 'https',
            ],
        )
        ->create();
    $tagPage = Page::factory()->site($site)->create();
    $pageUrl = PageUrl::factory()
        ->page($tagPage)
        ->site($site)
        ->language($language)
        ->create(['url' => '/topics/*/articles'])
        ->load('siteDomain');
    $tag = Tag::factory()->create([
        'slug' => ['en' => 'launch-news'],
    ]);
    $slug = $tag->getTranslation('slug', 'en');

    $tagPage->setRelation('pageUrl', $pageUrl);

    expect($tag->getUrl($tagPage, $language))->toBe('https://tags.example.test/topics/' . $slug . '/articles');
});

it('cascades taggable rows when deleting one shared tag', function (): void {
    $page = Page::factory()->create();
    $deletedTag = Tag::factory()->create();
    $retainedTag = Tag::factory()->create();

    $deletedTag->pages()->attach($page);
    $retainedTag->pages()->attach($page);

    $deletedTag->delete();

    expect(Tag::query()->whereKey($deletedTag->getKey())->exists())->toBeFalse()
        ->and(Tag::query()->whereKey($retainedTag->getKey())->exists())->toBeTrue()
        ->and(Page::query()->whereKey($page->getKey())->exists())->toBeTrue()
        ->and(Taggable::query()->where('tag_id', $deletedTag->getKey())->exists())->toBeFalse()
        ->and(Taggable::query()->where('tag_id', $retainedTag->getKey())->count())->toBe(1);
});

it('detaches taggable rows when deleting one tagged model', function (): void {
    Relation::morphMap(['tags-test-taggable-page' => TaggablePage::class], merge: true);

    $tag = Tag::factory()->create();
    $deletedPage = taggablePageFixture(Page::factory()->create());
    $retainedPage = taggablePageFixture(Page::factory()->create());

    $deletedPage->tags()->attach($tag);
    $retainedPage->tags()->attach($tag);

    $deletedPage->delete();

    expect(Tag::query()->whereKey($tag->getKey())->exists())->toBeTrue()
        ->and(Taggable::query()
            ->where('taggable_type', $deletedPage->getMorphClass())
            ->where('taggable_id', $deletedPage->getKey())
            ->exists())->toBeFalse()
        ->and(Taggable::query()
            ->where('taggable_type', $retainedPage->getMorphClass())
            ->where('taggable_id', $retainedPage->getKey())
            ->count())->toBe(1);
});

it('persists missing locale translations when falling back to the default locale', function (): void {
    app()->setLocale('en');

    $tag = Tag::factory()->create([
        'name' => ['en' => 'Latest News'],
        'slug' => ['en' => 'latest-news'],
        'type' => 'page',
    ]);

    $resolvedTag = Tag::findOrCreateFromString('Latest News', 'page', 'cy');
    $resolvedTag->refresh();

    expect($resolvedTag->getKey())->toBe($tag->getKey())
        ->and($resolvedTag->getTranslation('name', 'cy'))->toBe('Latest News')
        ->and($resolvedTag->getTranslation('slug', 'cy'))->toBe('latest-news');
});

it('creates tags for the owning site when site context is supplied', function (): void {
    app()->setLocale('en');

    $site = Site::factory()->withTranslations()->create();

    Tag::findOrCreateForSite(['Scoped News'], 'page', 'en', (int) $site->getKey());

    $tag = Tag::query()->where('type', 'page')->firstOrFail();

    expect($tag->site_id)->toBe($site->getKey());
});

it('reuses intentionally global tags instead of creating duplicate site tags', function (): void {
    app()->setLocale('en');

    $site = Site::factory()->withTranslations()->create();
    $globalTag = Tag::factory()->create([
        'name' => ['en' => 'Shared News'],
        'slug' => ['en' => 'shared-news'],
        'site_id' => null,
        'type' => 'page',
    ]);

    $resolvedTags = Tag::findOrCreateForSite(['Shared News'], 'page', 'en', (int) $site->getKey());

    expect(Tag::query()->count())->toBe(1)
        ->and($resolvedTags->pluck('id')->all())->toBe([$globalTag->getKey()]);
});

function taggablePageFixture(Page $page): TaggablePage
{
    $taggablePage = new TaggablePage;
    $taggablePage->setRawAttributes([
        ...$page->getAttributes(),
        'created_by' => $page->getAttribute('created_by'),
        'deleted_by' => $page->getAttribute('deleted_by'),
        'updated_by' => $page->getAttribute('updated_by'),
    ], sync: true);
    $taggablePage->exists = true;
    $taggablePage->setConnection($page->getConnectionName());

    return $taggablePage;
}
