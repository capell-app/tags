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

/**
 * @param  array<array-key, mixed>  $manifest
 * @return array<array-key, mixed>
 */
function tagsManifestArray(array $manifest, string $key): array
{
    $value = data_get($manifest, $key);

    throw_unless(is_array($value), RuntimeException::class, sprintf('Expected tags manifest [%s] to be an array.', $key));

    return $value;
}

/**
 * @param  array<array-key, mixed>  $manifest
 */
function tagsManifestString(array $manifest, string $key): string
{
    $value = data_get($manifest, $key);

    throw_unless(is_string($value), RuntimeException::class, sprintf('Expected tags manifest [%s] to be a string.', $key));

    return $value;
}

/**
 * @param  array<array-key, mixed>  $manifest
 */
function tagsManifestBool(array $manifest, string $key): bool
{
    $value = data_get($manifest, $key);

    throw_unless(is_bool($value), RuntimeException::class, sprintf('Expected tags manifest [%s] to be a boolean.', $key));

    return $value;
}

it('declares the shipped tags package manifest surfaces', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $composer = capell_json_file_array($packagePath . '/composer.json');
    $contributions = collect(tagsManifestArray($manifest, 'contributes'));
    $healthChecks = tagsManifestArray($manifest, 'healthChecks');

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/tags', $packagePath . '/capell.json');

    expect($manifest)
        ->toHaveKey('manifest-version', 3)
        ->toHaveKey('name', 'capell-app/tags')
        ->toHaveKey('namespace', 'Capell\\Tags')
        ->and(tagsManifestArray($manifest, 'surfaces'))->toContain('admin', 'console')
        ->and(tagsManifestArray($manifest, 'providers.runtime'))->toContain(TagsServiceProvider::class)
        ->and(tagsManifestArray($manifest, 'providers.install'))->toContain(ConsoleServiceProvider::class)
        ->and(tagsManifestArray($manifest, 'providers.admin'))->toContain(AdminServiceProvider::class)
        ->and(tagsManifestString($manifest, 'commands.install'))->toBe('capell:tags-install')
        ->and(tagsManifestBool($manifest, 'database.migrations'))->toBeTrue()
        ->and(tagsManifestArray($manifest, 'database.requiredTables'))->toBe(['tags', 'taggables'])
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
        ->and(tagsManifestString($healthChecks, '0.class'))->toBe(TagsHealthCheck::class)
        ->and(class_implements(TagResourceContribution::class))->toContain(RegistersExtensionAdminResource::class)
        ->and(class_implements(TagsModelsContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(TagsMigrationsContribution::class))->toContain(RunsExtensionMigration::class)
        ->and(class_implements(TagsHealthCheck::class))->toContain(ChecksExtensionHealth::class)
        ->and(tagsManifestArray($manifest, 'contributionTraceability.deferredContributions'))->toBe([]);
});

it('declares committed marketplace assets for required screenshot targets', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $screenshotContract = capell_json_file_array($packagePath . '/docs/screenshots.json');

    $marketplaceScreenshots = tagsManifestArray($manifest, 'marketplace.screenshots');
    $contractEntries = tagsManifestArray($screenshotContract, 'entries');

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
