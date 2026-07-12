<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Health\TagsHealthCheck;
use Capell\Tags\Models\Tag;
use Capell\Tags\Providers\AdminServiceProvider;
use Capell\Tags\Providers\TagsServiceProvider;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Schema;

it('reports a compatible capell api version', function (): void {
    expect(TagsHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('runs real diagnostics returning check results', function (): void {
    $results = TagsHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(4)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('passes when tables, tag model config, install status, and admin resource are healthy', function (): void {
    (new AdminServiceProvider(app()))->boot();

    $results = TagsHealthCheck::runDiagnostics();

    expect(TagsHealthCheck::passed())->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue()
        ->and(CapellAdmin::getAdminSurfaceRegistry()->resources())->toContain(TagResource::class);
});

it('fails the storage tables check when a tags table is missing', function (): void {
    Schema::dropIfExists('taggables');

    $check = new TagsHealthCheck;

    expect($check->missingTables())->toContain('taggables')
        ->and($check->storageTablesCheck()->passed)->toBeFalse()
        ->and(TagsHealthCheck::passed())->toBeFalse();
});

it('fails the tag model configuration check when spatie tags points elsewhere', function (): void {
    Config::set('tags.tag_model', 'Capell\\Blog\\Models\\Tag');

    $check = new TagsHealthCheck;

    expect($check->hasPackageTagModelConfiguration())->toBeFalse()
        ->and($check->tagModelConfigurationCheck()->passed)->toBeFalse();
});

it('fails the package install check when tags is not installed', function (): void {
    CapellCore::forcePackageInstalled(TagsServiceProvider::$packageName, false);

    try {
        $check = new TagsHealthCheck;

        expect($check->isPackageInstalled())->toBeFalse()
            ->and($check->packageInstalledCheck()->passed)->toBeFalse()
            ->and(TagsHealthCheck::passed())->toBeFalse();
    } finally {
        CapellCore::forcePackageInstalled(TagsServiceProvider::$packageName);
    }
});

it('fails the admin resource check when the tags resource is not registered', function (): void {
    CapellAdmin::clearAdminSurfaceContributions();

    try {
        $check = new TagsHealthCheck;

        expect($check->hasRegisteredAdminResource())->toBeFalse()
            ->and($check->adminResourceRegistrationCheck()->passed)->toBeFalse()
            ->and(TagsHealthCheck::passed())->toBeFalse();
    } finally {
        (new AdminServiceProvider(app()))->boot();
    }
});

it('confirms the package tag model is configured', function (): void {
    $check = new TagsHealthCheck;

    expect($check->hasPackageTagModelConfiguration())->toBeTrue()
        ->and(config('tags.tag_model'))->toBe(Tag::class)
        ->and($check->tagModelConfigurationCheck()->passed)->toBeTrue();
});
