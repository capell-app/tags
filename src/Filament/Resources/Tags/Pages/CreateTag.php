<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Resources\Tags\Pages;

use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Tags\Enums\ResourceEnum;
use Filament\Resources\Pages\CreateRecord;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;
use Override;

class CreateTag extends CreateRecord
{
    use Translatable;

    #[Override]
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resource(ResourceEnum::Tag);
    }

    #[Override]
    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
