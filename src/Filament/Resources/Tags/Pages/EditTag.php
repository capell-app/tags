<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Resources\Tags\Pages;

use Capell\Admin\Filament\Actions\DeleteAction;
use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Tags\Enums\ResourceEnum;
use Capell\Tags\Filament\Resources\Tags\Schemas\TagForm;
use Capell\Tags\Models\Tag;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\EditRecord\Concerns\Translatable;
use Override;

class EditTag extends EditRecord
{
    use Translatable {
        handleRecordUpdate as translatableHandleRecordUpdate;
    }

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

        $recordTitle = $this->getRecordTitle();

        return new HtmlString(__('capell-tags::generic.edit_tag_record', [
            'name' => Str::limit($recordTitle instanceof Htmlable ? $recordTitle->toHtml() : $recordTitle, 40),
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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        TagForm::assertUniqueSlug($data, $record instanceof Tag ? $record : null, $this->activeLocale);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        TagForm::assertUniqueSlug($data, $record instanceof Tag ? $record : null, $this->activeLocale);

        return $this->translatableHandleRecordUpdate($record, $data);
    }
}
