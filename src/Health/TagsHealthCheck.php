<?php

declare(strict_types=1);

namespace Capell\Tags\Health;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Capell\Tags\Filament\Resources\Tags\TagResource;
use Capell\Tags\Models\Tag;
use Capell\Tags\Providers\TagsServiceProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TagsHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->storageTablesCheck(),
            $check->tagModelConfigurationCheck(),
            $check->packageInstalledCheck(),
            $check->adminResourceRegistrationCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    /**
     * Asserts the Spatie tags storage tables are present.
     */
    public function storageTablesCheck(): DoctorCheckResultData
    {
        $missingTables = $this->missingTables();

        return new DoctorCheckResultData(
            label: 'Tags storage tables',
            passed: $missingTables === [],
            message: $missingTables === []
                ? 'The tags and taggables tables are present.'
                : 'Missing tables: ' . implode(', ', $missingTables) . '.',
            remediation: $missingTables === []
                ? null
                : 'Run the Capell migrations to create the tags and taggables tables.',
        );
    }

    /**
     * Asserts Spatie Tags resolves to the Capell Tags model.
     */
    public function tagModelConfigurationCheck(): DoctorCheckResultData
    {
        $configured = $this->hasPackageTagModelConfiguration();

        return new DoctorCheckResultData(
            label: 'Tags model configuration',
            passed: $configured,
            message: $configured
                ? 'The tags.tag_model configuration resolves to the Capell Tags model.'
                : 'The tags.tag_model configuration does not resolve to the Capell Tags model.',
            remediation: $configured
                ? null
                : 'Set tags.tag_model to ' . Tag::class . ' or republish the Capell Tags configuration.',
        );
    }

    /**
     * Asserts Capell sees the package as installed.
     */
    public function packageInstalledCheck(): DoctorCheckResultData
    {
        $installed = $this->isPackageInstalled();

        return new DoctorCheckResultData(
            label: 'Tags package install status',
            passed: $installed,
            message: $installed
                ? 'The Tags package is marked as installed in Capell.'
                : 'The Tags package is not marked as installed in Capell.',
            remediation: $installed
                ? null
                : 'Install the Tags package through Capell package management or run the Tags install command.',
        );
    }

    /**
     * Asserts the Tags admin resource is contributed to the admin surface.
     */
    public function adminResourceRegistrationCheck(): DoctorCheckResultData
    {
        $registered = $this->hasRegisteredAdminResource();

        return new DoctorCheckResultData(
            label: 'Tags admin resource registration',
            passed: $registered,
            message: $registered
                ? 'The Tags admin resource is registered in the Capell admin surface.'
                : 'The Tags admin resource is not registered in the Capell admin surface.',
            remediation: $registered
                ? null
                : 'Ensure Tags AdminServiceProvider is loaded after the package is installed.',
        );
    }

    /**
     * @return list<string>
     */
    public function missingTables(): array
    {
        return array_values(collect($this->requiredTableNames())
            ->reject(static fn (string $tableName): bool => Schema::hasTable($tableName))
            ->values()
            ->all());
    }

    public function hasPackageTagModelConfiguration(): bool
    {
        return config('tags.tag_model') === Tag::class;
    }

    public function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(TagsServiceProvider::$packageName);
    }

    public function hasRegisteredAdminResource(): bool
    {
        try {
            return in_array(TagResource::class, CapellAdmin::getAdminSurfaceRegistry()->resources(), true);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function requiredTableNames(): array
    {
        $taggablesTable = config('tags.taggable.table_name', 'taggables');

        return [
            'tags',
            is_string($taggablesTable) && $taggablesTable !== '' ? $taggablesTable : 'taggables',
        ];
    }
}
