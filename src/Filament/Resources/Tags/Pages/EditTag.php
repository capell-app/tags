<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Resources\Tags\Pages;

use Capell\Admin\Filament\Actions\DeleteAction;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Tags\Enums\ResourceEnum;
use Capell\Tags\Models\Tag;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
use Override;

class EditTag extends EditRecord
{
    use Translatable;

    #[Override]
    public static function getResource(): string
    {
        return AdminSurfaceLookup::resource(ResourceEnum::Tag);
    }

    #[Override]
    public function getTitle(): string|Htmlable
    {
        if (filled(static::$title)) {
            return static::$title;
        }

        return new HtmlString(__('capell-tags::generic.edit_tag_record', [
            'name' => Str::limit($this->getRecordTitle(), 40),
        ]));
    }

    #[Override]
    protected function getActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
            ActionGroup::make([
                CreateAction::make()
                    ->record($this->getRecord())
                    ->url(fn (Tag $record): string => static::getResource()::getUrl('create')),
            ]),
        ];
    }
}
