<?php

declare(strict_types=1);

namespace Capell\Tags\Console\Commands;

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Install\ConsoleProgressReporter;
use Capell\Tags\Actions\InstallTagsPackageAction;
use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $description = 'Install tags package';

    protected $signature = 'capell:tags-install';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        InstallTagsPackageAction::run(CapellCore::getPackage('capell-app/tags'), [], new ConsoleProgressReporter($this));

        $this->newLine();
        $this->info('Capell Tags installed successfully.');

        return self::SUCCESS;
    }
}
