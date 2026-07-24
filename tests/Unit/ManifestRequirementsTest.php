<?php

declare(strict_types=1);

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RegistersExtensionAdminResource;
use Capell\Core\Contracts\Extensions\RunsExtensionMigration;
use Capell\Core\Support\Manifest\ManifestValidator;
use Capell\Tags\Console\Commands\InstallCommand;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Health\TagsHealthCheck;
use Capell\Tags\Manifest\TagResourceContribution;
use Capell\Tags\Manifest\TagsConsoleCommandsContribution;
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
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $composer = capell_json_file_array($packagePath . '/composer.json');
    $contributions = data_get($manifest, 'contributes');
    $healthChecks = data_get($manifest, 'healthChecks');
    $permissions = [
        'ViewAny:Tag',
        'View:Tag',
        'Create:Tag',
        'Update:Tag',
        'Delete:Tag',
        'DeleteAny:Tag',
        'Restore:Tag',
        'RestoreAny:Tag',
        'ForceDelete:Tag',
        'ForceDeleteAny:Tag',
        'Replicate:Tag',
        'Reorder:Tag',
    ];

    throw_unless(is_array($contributions), RuntimeException::class, 'Expected Tags manifest contributions.');
    throw_unless(is_array($healthChecks), RuntimeException::class, 'Expected Tags health checks.');

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/tags', $packagePath . '/capell.json');

    expect($manifest)
        ->toHaveKey('manifest-version', 3)
        ->toHaveKey('name', 'capell-app/tags')
        ->toHaveKey('namespace', 'Capell\\Tags')
        ->and(data_get($manifest, 'dependencies.requires', []))->not->toContain('capell-app/publishing-studio')
        ->and(data_get($manifest, 'dependencies.supports', []))->toContain('capell-app/publishing-studio')
        ->and(data_get($composer, 'require', []))->not->toHaveKey('capell-app/publishing-studio')
        ->and(data_get($composer, 'suggest.capell-app/publishing-studio'))->toBeString()
        ->and(data_get($manifest, 'surfaces', []))->toContain('admin', 'console')
        ->and(data_get($manifest, 'providers.runtime', []))->toContain(TagsServiceProvider::class)
        ->and(data_get($manifest, 'providers.install', []))->toContain(ConsoleServiceProvider::class)
        ->and(data_get($manifest, 'providers.admin', []))->toContain(AdminServiceProvider::class)
        ->and(data_get($manifest, 'commands.install'))->toBe('capell:tags-install')
        ->and(data_get($manifest, 'database.migrations'))->toBeTrue()
        ->and(data_get($manifest, 'database.requiredTables', []))->toBe(['tags', 'taggables'])
        ->and(data_get($manifest, 'permissions'))->toBe($permissions)
        ->and(data_get($manifest, 'security.adminSurface.authorization'))->toBe('permissions')
        ->and(data_get($manifest, 'security.adminSurface.permissions'))->toBe($permissions)
        ->and(collect($contributions))->toContain([
            'type' => 'admin-resource',
            'class' => TagResourceContribution::class,
            'resourceClass' => TagResource::class,
            'label' => 'Tags taxonomy admin resource.',
        ])
        ->and(collect($contributions))->toContain([
            'type' => 'model',
            'class' => TagsModelsContribution::class,
            'modelClasses' => [
                Tag::class,
                Taggable::class,
            ],
        ])
        ->and(collect($contributions))->toContain([
            'type' => 'migration',
            'class' => TagsMigrationsContribution::class,
            'tables' => ['tags', 'taggables'],
            'migrationFiles' => [
                '2026_05_10_190872_01_alter_tags_table',
                '2026_06_04_000001_add_type_site_id_index_to_tags_table',
                '2026_07_10_000001_add_merged_slug_aliases_to_tags_table',
            ],
        ])
        ->and(collect($contributions))->toContain([
            'type' => 'console-command',
            'class' => TagsConsoleCommandsContribution::class,
            'commands' => ['capell:tags-install'],
            'commandClasses' => [InstallCommand::class],
        ])
        ->and(collect($contributions))->toContain([
            'type' => 'health-check',
            'class' => TagsHealthCheck::class,
        ])
        ->and(data_get($healthChecks, '0.class'))->toBe(TagsHealthCheck::class)
        ->and(class_implements(TagResourceContribution::class))->toContain(RegistersExtensionAdminResource::class)
        ->and(class_implements(TagsConsoleCommandsContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(TagsModelsContribution::class))->toContain(ExtensionContribution::class)
        ->and(class_implements(TagsMigrationsContribution::class))->toContain(RunsExtensionMigration::class)
        ->and(class_implements(TagsHealthCheck::class))->toContain(ChecksExtensionHealth::class)
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->toBe([]);
});

it('declares committed marketplace assets for required screenshot targets', function (): void {
    $packagePath = dirname(__DIR__, 2);
    $manifest = capell_json_file_array($packagePath . '/capell.json');
    $screenshotContract = capell_json_file_array($packagePath . '/docs/screenshots.json');

    $marketplaceScreenshots = data_get($manifest, 'marketplace.screenshots');
    $contractEntries = data_get($screenshotContract, 'entries');

    throw_unless(is_array($marketplaceScreenshots), RuntimeException::class, 'Expected Tags marketplace screenshots.');
    throw_unless(is_array($contractEntries), RuntimeException::class, 'Expected Tags screenshot contract entries.');

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
