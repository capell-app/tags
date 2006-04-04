<?php

declare(strict_types=1);

namespace Capell\Tags\Providers;

use Capell\Core\Enums\PackageTypeEnum;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Page;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Tags\Console\Commands\InstallCommand;
use Capell\Tags\Models\Tag;
use Capell\Tags\Policies\TagPolicy;
use Capell\Tags\Support\TagModelRegistrar;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\LaravelPackageTools\Package;

final class TagsServiceProvider extends AbstractPackageServiceProvider
{
    public static string $name = 'capell-tags';

    public static string $packageName = 'capell-app/tags';

    public static PackageTypeEnum $type = PackageTypeEnum::Plugin;

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasCommands([
                InstallCommand::class,
            ])
            ->hasViews()
            ->hasTranslations();
    }

    #[Override]
    public function registeringPackage(): void
    {
        parent::registeringPackage();

        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this->repairLegacyTagModelConfig();
            TagModelRegistrar::register();
            TagModelRegistrar::registerTaggable(Page::class);
            Gate::policy(Tag::class, TagPolicy::class);
        });

        $this->app->booted(function (): void {
            $this->registerPublishCommands();
        });
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(self::$packageName);
    }

    private function repairLegacyTagModelConfig(): void
    {
        $configuredModel = config('tags.tag_model');

        if ($configuredModel === Tag::class) {
            return;
        }

        if (is_string($configuredModel) && class_exists($configuredModel)) {
            return;
        }

        config(['tags.tag_model' => Tag::class]);
    }

    private function registerPublishCommands(): self
    {
        if (! isset($this->package)) {
            return $this;
        }

        $this->publishes([
            $this->package->basePath('/../publishes/config/') => config_path(),
        ], 'capell-tags-config');

        return $this;
    }
}
