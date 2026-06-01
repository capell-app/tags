<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Concerns\HasTags;
use Capell\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;

it('keeps the package taggable trait analysed in isolation', function (): void {
    $taggable = new class extends Page
    {
        use HasTags;

        public function getMorphClass(): string
        {
            return 'page';
        }
    };

    expect(class_uses_recursive($taggable))->toContain(HasTags::class);
});

it('syncs typed tags for every loaded page language in the owning site scope', function (): void {
    app()->setLocale('en');

    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()
        ->site($site)
        ->withTranslations($site->languages->all())
        ->create();

    $taggable = new class extends Page
    {
        use HasTags;
    };
    $taggable->setRawAttributes($page->getAttributes(), sync: true);
    $taggable->exists = true;
    $taggable->setRelation('languages', $site->languages);
    Relation::morphMap(['coverage-gap-taggable-languages' => $taggable::class], merge: true);

    $result = $taggable->syncTagsWithType(['Launch News'], 'page');

    $tags = Tag::query()->where('type', 'page')->orderBy('id')->get();

    expect($result)->toBe($taggable)
        ->and($tags)->toHaveCount($site->languages->count())
        ->and($tags->pluck('site_id')->unique()->all())->toBe([$site->getKey()])
        ->and($tags->pluck('id')->all())->toEqualCanonicalizing($taggable->tagsWithType('page')->pluck('id')->all());
});

it('syncs typed tags from a loaded site relation when no language relation is loaded', function (): void {
    app()->setLocale('en');

    $site = Site::factory()->withTranslations()->create();
    $page = Page::factory()->site($site)->create();

    $taggable = new class extends Page
    {
        use HasTags;

        public function getMorphClass(): string
        {
            return 'page';
        }
    };
    $attributes = $page->getAttributes();
    unset($attributes['site_id']);

    $taggable->setRawAttributes([...$attributes, 'site_id' => null], sync: true);
    $taggable->exists = true;
    $taggable->setRelation('site', $site);
    $taggable->setRelation('languages', new EloquentCollection);
    Relation::morphMap(['coverage-gap-taggable-site' => $taggable::class], merge: true);

    $taggable->syncTagsWithType(['Relation Scoped'], 'page');

    $tag = Tag::query()->where('type', 'page')->firstOrFail();

    expect($tag->site_id)->toBe($site->getKey())
        ->and($taggable->tagsWithType('page')->pluck('id')->all())->toBe([$tag->getKey()]);
});
