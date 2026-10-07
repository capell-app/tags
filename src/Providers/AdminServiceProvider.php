<?php

declare(strict_types=1);

namespace Capell\Tags\Providers;

use Capell\Admin\Contracts\Extenders\PageTableExtender;
use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\RegistersInstalledRuntime;
use Capell\Tags\Enums\ResourceEnum;
use Capell\Tags\Filament\Extenders\PageTagsPageTableExtender;
use Illuminate\Support\ServiceProvider;
use Override;

class AdminServiceProvider extends ServiceProvider
{
    use RegistersInstalledRuntime;

    private bool $resourcesRegistered = false;

    #[Override]
    public function register(): void
    {
        $this->registerInstalledRuntime(TagsServiceProvider::$packageName, 'admin');

        $this->app->booting(function (): void {
            if ($this->isPackageInstalled()) {
                $this->registerResources();
            }
        });
    }

    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(TagsServiceProvider::$packageName);
    }

    protected function bootInstalledRuntime(): void
    {
        $this
            ->registerResources()
            ->registerPageTableExtender();
    }

    private function registerResources(): self
    {
        if ($this->resourcesRegistered) {
            return $this;
        }

        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
            class: ResourceEnum::Tag->value,
            group: ResourceEnum::Tag->name,
        ));
        $this->resourcesRegistered = true;

        return $this;
    }

    private function registerPageTableExtender(): self
    {
        $this->app->tag([PageTagsPageTableExtender::class], PageTableExtender::TAG);

        return $this;
    }
}
