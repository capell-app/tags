<?php

declare(strict_types=1);

use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Capell\Tags\Providers\TagsServiceProvider;

it('Tag class exists', function (): void {
    expect(class_exists(Tag::class))->toBeTrue();
});

it('TagsServiceProvider class exists', function (): void {
    expect(class_exists(TagsServiceProvider::class))->toBeTrue();
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

    expect($caseNames)->toContain('Article');
    expect($caseNames)->toContain('Content');
    expect($caseNames)->toContain('Page');
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
