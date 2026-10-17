<?php

declare(strict_types=1);

use Capell\Core\Actions\InstallPackageAction;
use Capell\Core\Actions\RuntimeRefresh\RefreshInstalledPackageRuntimeAction;
use Capell\Core\Facades\CapellCore;
use Capell\Tags\Providers\TagsServiceProvider;
use Capell\Tests\Support\InstalledRuntimeParity;
use Capell\Tests\Support\InstalledRuntimeReceiptWitness;
use Capell\Tests\Support\InstalledRuntimeState;
use Capell\Tests\Support\InstalledRuntimeTestCase;
use Illuminate\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

it('matches fresh installed boot after in-process installation and repeated refresh', function (): void {
    InstalledRuntimeParity::assertPackage('tags');
});

it('publishes configuration synchronously before installed runtime activation', function (): void {
    InstalledRuntimeState::run(static function (): void {
        unset(ServiceProvider::$publishes[TagsServiceProvider::class], ServiceProvider::$publishGroups['capell-tags-config']);
        $application = new Application(app()->basePath());
        $provider = new TagsServiceProvider($application);
        $package = new Package;
        $package->setBasePath(dirname(__DIR__, 3) . '/src');

        $provider->configurePackage($package);
        new ReflectionProperty(PackageServiceProvider::class, 'package')->setValue($provider, $package);

        $provider->packageBooted();

        expect($application->isBooted())->toBeFalse()
            ->and(ServiceProvider::pathsToPublish(TagsServiceProvider::class, 'capell-tags-config'))->toBe([
                $package->basePath('/../publishes/config/') => config_path(),
            ]);
    });
});

it('retains the same receipt order on fresh boot and late installation', function (): void {
    InstalledRuntimeState::run(static function (): void {
        $manifest = InstalledRuntimeParity::manifest('tags');
        $fresh = InstalledRuntimeTestCase::boot($manifest, true, sourceRoot: dirname(__DIR__, 3));
        $expected = $fresh->make(InstalledRuntimeReceiptWitness::class)->emissions($manifest['name'], $manifest['namespace']);
        expect($expected)->toHaveCount(3);

        $late = InstalledRuntimeTestCase::boot($manifest, false, sourceRoot: dirname(__DIR__, 3));
        InstalledRuntimeTestCase::prepareLateInstallMigrations($late);
        $package = CapellCore::getPackage('capell-app/tags');
        InstallPackageAction::run($package);
        expect($late->make(InstalledRuntimeReceiptWitness::class)->emissions($manifest['name'], $manifest['namespace']))->toBe($expected);
        RefreshInstalledPackageRuntimeAction::run($package);
        RefreshInstalledPackageRuntimeAction::run($package);
        expect($late->make(InstalledRuntimeReceiptWitness::class)->emissions($manifest['name'], $manifest['namespace']))->toBe($expected);
    });
});
