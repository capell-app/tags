<?php

declare(strict_types=1);

use Capell\Core\Models\Site;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Actions\ResolveTagBySlugAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;

it('preserves source slugs as redirect aliases when tags merge', function (): void {
    $site = Site::factory()->create();
    $target = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => ['en' => 'Laravel', 'de' => 'Laravel'],
        'slug' => ['en' => 'laravel', 'de' => 'laravel-de'],
        'merged_slug_aliases' => null,
    ]);
    $source = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'name' => ['en' => 'PHP Framework', 'de' => 'PHP Framework'],
        'slug' => ['en' => 'php-framework', 'de' => 'php-framework-de'],
        'merged_slug_aliases' => [
            'en' => ['legacy-framework'],
        ],
    ]);

    expect(MergeTagsAction::run($target, collect([$source])))->toBe(1);

    $target->refresh();

    expect($target->merged_slug_aliases)->toBe([
        'de' => ['php-framework-de'],
        'en' => ['legacy-framework', 'php-framework'],
    ]);

    $resolution = ResolveTagBySlugAction::run(
        slug: 'php-framework',
        siteId: (int) $site->getKey(),
        locale: 'en',
        type: TagTypeEnum::Page->value,
    );

    expect($resolution?->tag->is($target))->toBeTrue()
        ->and($resolution?->canonicalSlug)->toBe('laravel')
        ->and($resolution?->shouldRedirect())->toBeTrue();
});

it('prefers a current slug over an older merge alias', function (): void {
    $site = Site::factory()->create();
    $target = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'slug' => ['en' => 'target-topic'],
        'merged_slug_aliases' => [
            'en' => ['reused-topic'],
        ],
    ]);
    $current = Tag::factory()->site($site)->type(TagTypeEnum::Page)->create([
        'slug' => ['en' => 'reused-topic'],
    ]);

    $resolution = ResolveTagBySlugAction::run(
        slug: 'reused-topic',
        siteId: (int) $site->getKey(),
        locale: 'en',
        type: TagTypeEnum::Page->value,
    );

    expect($resolution?->tag->is($current))->toBeTrue()
        ->and($resolution?->tag->is($target))->toBeFalse()
        ->and($resolution?->shouldRedirect())->toBeFalse();
});
