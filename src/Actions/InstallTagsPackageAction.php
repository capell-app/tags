<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Core\Actions\Install\PublishPackageMigrationsAction;
use Capell\Core\Actions\Install\RunArtisanCommandAction;
use Capell\Core\Actions\Install\RunMigrationsAction;
use Capell\Core\Actions\PublishMigrationsAction;
use Capell\Core\Contracts\PackageLifecycleAction;
use Capell\Core\Contracts\ProgressReporter;
use Capell\Core\Data\PackageData;
use Capell\Core\Support\Install\NullProgressReporter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use RuntimeException;

final class InstallTagsPackageAction implements PackageLifecycleAction
{
    use AsFake;
    use AsObject;

    public function handle(PackageData $package, array $arguments = [], ?ProgressReporter $reporter = null): void
    {
        $reporter ??= new NullProgressReporter;

        RunArtisanCommandAction::run('vendor:publish', ['--tag' => 'capell-tags-config'], $reporter);
        $this->publishVendorTagMigrations($reporter);

        PublishPackageMigrationsAction::run(new Collection([$package->name => $package]), $reporter, true, false);
        RunMigrationsAction::run($reporter);

        $reporter->report('Capell Tags installed successfully.');
    }

    private function publishVendorTagMigrations(ProgressReporter $reporter): void
    {
        if (Schema::hasTable('tags') || Schema::hasTable('taggables')) {
            return;
        }

        $result = PublishMigrationsAction::run('migrations', [base_path('vendor/spatie/laravel-tags/database/migrations/create_tag_tables.php.stub')]);

        foreach ($result->warnings as $warning) {
            $reporter->report($warning);
        }

        if (! $result->successful()) {
            throw new RuntimeException(implode("\n", $result->errors));
        }

        foreach ($result->lines as $line) {
            $reporter->report($line);
        }
    }
}
