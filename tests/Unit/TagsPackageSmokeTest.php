<?php

declare(strict_types=1);

use Capell\Tags\Actions\BuildTagCloudAction;
use Capell\Tags\Actions\FindRelatedTaggablesAction;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Filament\Resources\Tags\Schemas\TagForm;
use Capell\Tags\Models\Tag;
use Capell\Tags\Providers\TagsServiceProvider;
use Filament\Forms\Components\Select;

it('Tag class exists', function (): void {
    expect(class_exists(Tag::class))->toBeTrue();
});

it('TagsServiceProvider class exists', function (): void {
    expect(class_exists(TagsServiceProvider::class))->toBeTrue();
});

it('exposes tag cloud and related content helpers as package actions', function (): void {
    expect(class_exists(BuildTagCloudAction::class))->toBeTrue()
        ->and(class_exists(FindRelatedTaggablesAction::class))->toBeTrue()
        ->and(class_exists(MergeTagsAction::class))->toBeTrue();
});

it('repairs stale published tag model config', function (): void {
    config(['tags.tag_model' => 'Capell\\Blog\\Models\\Tag']);

    (new TagsServiceProvider(app()))->registeringPackage();

    expect(config('tags.tag_model'))->toBe(Tag::class);
});

it('TagTypeEnum is a backed enum with expected cases', function (): void {
    expect(enum_exists(TagTypeEnum::class))->toBeTrue();

    $cases = TagTypeEnum::cases();
    $caseNames = array_map(fn (TagTypeEnum $case): string => $case->name, $cases);

    expect($caseNames)->toContain('Article')
        ->and($caseNames)->toContain('Content')
        ->and($caseNames)->toContain('Page')
        ->and(TagTypeEnum::Article->getLabel())->toBe('Article')
        ->and(TagTypeEnum::Content->getLabel())->toBe('Content')
        ->and(TagTypeEnum::Page->getLabel())->toBe('Page');
});

it('uses the tag type enum as the admin form source of truth', function (): void {
    $typeSelect = TagForm::typeSelect();

    expect($typeSelect)->toBeInstanceOf(Select::class)
        ->and(tagsPackageSmokeTestSelectOptions($typeSelect))->toBe(TagTypeEnum::class);
});

it('creates factory tags with supported enum-backed types', function (): void {
    $allowedTypes = array_map(
        static fn (TagTypeEnum $type): string => $type->value,
        TagTypeEnum::cases(),
    );

    $tags = Tag::factory()->count(10)->create();

    expect($tags->pluck('type')->unique()->values()->all())
        ->each->toBeIn($allowedTypes);
});

it('declares taxonomy capabilities in the package manifest', function (): void {
    /** @var array{capabilities: list<string>, performance: array{cacheTags: list<string>}} $manifest */
    $manifest = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/capell.json'), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['capabilities'])->toContain(
        'tags-taxonomy',
        'tags-multilingual',
        'tags-site-scoped',
        'tags-polymorphic-taggables',
        'tags-merge-dedupe',
        'tags-related-content',
        'tags-tag-cloud',
        'tags-reusable-input',
    )
        ->and($manifest['actions'])->toHaveKeys([
            'buildTagCloud',
            'findRelatedTaggables',
            'mergeTags',
        ])
        ->and($manifest['performance']['cacheTags'])->toContain('tags');
});

it('does not depend on layout builder translation keys for tag admin labels', function (): void {
    $packageRoot = dirname(__DIR__, 2);
    $files = [
        $packageRoot . '/src/Filament/Resources/Tags/Schemas/TagForm.php',
        $packageRoot . '/src/Filament/Resources/Tags/Tables/TagsTable.php',
        $packageRoot . '/src/Filament/Resources/Tags/Pages/EditTag.php',
    ];

    foreach ($files as $file) {
        expect(file_get_contents($file))->not->toContain('capell-layout-builder::');
    }
});

function tagsPackageSmokeTestSelectOptions(?Select $select): mixed
{
    if (! $select instanceof Select) {
        return null;
    }

    $reflection = new ReflectionClass($select);

    while (! $reflection->hasProperty('options') && ($parent = $reflection->getParentClass()) instanceof ReflectionClass) {
        $reflection = $parent;
    }

    if (! $reflection->hasProperty('options')) {
        return null;
    }

    $property = $reflection->getProperty('options');

    return $property->getValue($select);
}
