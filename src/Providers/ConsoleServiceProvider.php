<?php

declare(strict_types=1);

namespace Capell\Tags\Providers;

use Capell\Tags\Console\Commands\InstallCommand;
use Illuminate\Support\ServiceProvider;
use Override;

final class ConsoleServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                InstallCommand::class,
            ]);
        }
    }
}
