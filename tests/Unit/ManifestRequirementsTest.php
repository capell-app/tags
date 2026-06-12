<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionAdminResource;
use Capell\Core\Contracts\Extensions\RunsExtensionMigration;
use Capell\Core\Support\Manifest\ManifestValidator;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Health\TagsHealthCheck;
use Capell\Tags\Manifest\TagResourceContribution;
use Capell\Tags\Manifest\TagsMigrationsContribution;
use Capell\Tags\Manifest\TagsModelsContribution;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Capell\Tags\Providers\AdminServiceProvider;
use Capell\Tags\Providers\ConsoleServiceProvider;
use Capell\Tags\Providers\TagsServiceProvider;
use Illuminate\Support\Facades\File;

it('declares the shipped tags package manifest surfaces', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = json_decode(File::get($packagePath . '/capell.json'), true, flags: JSON_THROW_ON_ERROR);
    $composer = json_decode(File::get($packagePath . '/composer.json'), true, flags: JSON_THROW_ON_ERROR);
    $contributions = collect($manifest['contributes']);

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/tags', $packagePath . '/capell.json');

    expect($manifest)
        ->toHaveKey('manifest-version', 3)
        ->toHaveKey('name', 'capell-app/tags')
        ->toHaveKey('namespace', 'Capell\\Tags')
        ->and($manifest['surfaces'])->toContain('admin', 'console')
        ->and($manifest['providers']['runtime'])->toContain(TagsServiceProvider::class)
        ->and($manifest['providers']['install'])->toContain(ConsoleServiceProvider::class)
        ->and($manifest['providers']['admin'])->toContain(AdminServiceProvider::class)
        ->and($manifest['commands']['install'])->toBe('capell:tags-install')
        ->and($manifest['database']['migrations'])->toBeTrue()
        ->and($manifest['database']['requiredTables'])->toBe(['tags', 'taggables'])
        ->and($contributions)->toContain([
            'type' => 'admin-resource',
            'class' => TagResourceContribution::class,
            'resourceClass' => TagResource::class,
            'label' => 'Tags taxonomy admin resource.',
        ])
        ->and($contributions)->toContain([
            'type' => 'model',
            'class' => TagsModelsContribution::class,
            'modelClasses' => [
                Tag::class,
                Taggable::class,
            ],
        ])
        ->and($contributions)->toContain([
            'type' => 'migration',
            'class' => TagsMigrationsContribution::class,
            'tables' => ['tags', 'taggables'],
        ])
        ->and($contributions)->toContain([
            'type' => 'health-check',
            'class' => TagsHealthCheck::class,
        ])
        ->and($manifest['healthChecks'][0]['class'])->toBe(TagsHealthCheck::class)
        ->and(class_implements(TagResourceContribution::class))->toContain(RegistersExtensionAdminResource::class)
        ->and(class_implements(TagsModelsContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(TagsMigrationsContribution::class))->toContain(RunsExtensionMigration::class)
        ->and(class_implements(TagsHealthCheck::class))->toContain(ChecksExtensionHealth::class)
        ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([]);
});

it('declares committed marketplace assets for required screenshot targets', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = json_decode(File::get($packagePath . '/capell.json'), true, flags: JSON_THROW_ON_ERROR);
    $screenshotContract = json_decode(File::get($packagePath . '/docs/screenshots.json'), true, flags: JSON_THROW_ON_ERROR);

    $marketplaceScreenshots = $manifest['marketplace']['screenshots'] ?? [];
    $contractEntries = $screenshotContract['entries'] ?? [];

    throw_unless(is_array($marketplaceScreenshots), RuntimeException::class, 'Tags marketplace screenshots must be an array.');
    throw_unless(is_array($contractEntries), RuntimeException::class, 'Tags screenshot contract entries must be an array.');

    $marketplaceScreenshotPaths = [];

    foreach ($marketplaceScreenshots as $marketplaceScreenshot) {
        throw_unless(is_array($marketplaceScreenshot), RuntimeException::class, 'Tags marketplace screenshot entries must be arrays.');

        $path = $marketplaceScreenshot['path'] ?? null;
        $alt = $marketplaceScreenshot['alt'] ?? null;
        $caption = $marketplaceScreenshot['caption'] ?? null;

        throw_unless(is_string($path), RuntimeException::class, 'Tags marketplace screenshot paths must be strings.');
        throw_unless(is_string($alt), RuntimeException::class, 'Tags marketplace screenshot alt text must be strings.');
        throw_unless(is_string($caption), RuntimeException::class, 'Tags marketplace screenshot captions must be strings.');

        $marketplaceScreenshotPaths[] = $path;

        expect(str_starts_with($path, 'docs/assets/marketplace/') || str_starts_with($path, 'docs/screenshots/'))->toBeTrue()
            ->and(File::exists($packagePath . '/' . $path))->toBeTrue()
            ->and(strlen(trim($alt)))->toBeGreaterThanOrEqual(12)
            ->and(strlen(trim($caption)))->toBeGreaterThanOrEqual(12);
    }

    $requiredScreenshotPaths = [];

    foreach ($contractEntries as $contractEntry) {
        if (! is_array($contractEntry) || ($contractEntry['required'] ?? false) !== true) {
            continue;
        }

        $screenshotPath = $contractEntry['screenshotPath'] ?? null;

        throw_unless(is_string($screenshotPath), RuntimeException::class, 'Required Tags screenshot entries must have screenshot paths.');

        $requiredScreenshotPaths[] = str_replace('packages/tags/', '', $screenshotPath);
    }

    expect($marketplaceScreenshotPaths)
        ->toContain('docs/assets/marketplace/extension-card.jpg')
        ->toContain(...$requiredScreenshotPaths);
});
