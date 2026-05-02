<?php

declare(strict_types=1);

namespace Capell\Tags\Providers;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Tags\Enums\ResourceEnum;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (! $this->isPackageInstalled()) {
            return;
        }

        CapellAdmin::registerResource(ResourceEnum::Tag->name, class: ResourceEnum::Tag->value);
    }

    private function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(TagsServiceProvider::$packageName);
    }
}
