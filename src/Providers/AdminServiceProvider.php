<?php

declare(strict_types=1);

namespace Capell\Tags\Providers;

use Capell\Admin\Contracts\Extenders\PageTableExtender;
use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Tags\Enums\ResourceEnum;
use Capell\Tags\Filament\Extenders\PageTagsPageTableExtender;
use Illuminate\Support\ServiceProvider;
use Override;

class AdminServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->booting(function (): void {
            if ($this->isPackageInstalled()) {
                $this->registerResources();
            }
        });
    }

    public function boot(): void
    {
        if (! $this->isPackageInstalled()) {
            return;
        }

        $this
            ->registerResources()
            ->registerPageTableExtender();
    }

    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(TagsServiceProvider::$packageName);
    }

    private function registerResources(): self
    {
        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
            class: ResourceEnum::Tag->value,
            group: ResourceEnum::Tag->name,
        ));

        return $this;
    }

    private function registerPageTableExtender(): self
    {
        $this->app->tag([PageTagsPageTableExtender::class], PageTableExtender::TAG);

        return $this;
    }
}
