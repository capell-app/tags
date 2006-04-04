<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Resources\Tags\Pages;

use Capell\Admin\Support\AdminSurfaceLookup;
use Capell\Tags\Enums\ResourceEnum;
use Capell\Tags\Filament\Resources\Tags\Schemas\TagForm;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use LaraZeus\SpatieTranslatable\Actions\LocaleSwitcher;
use LaraZeus\SpatieTranslatable\Resources\Pages\CreateRecord\Concerns\Translatable;
use Override;

class CreateTag extends CreateRecord
{
    use Translatable {
        handleRecordCreation as translatableHandleRecordCreation;
    }

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

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->validateSlug($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        $this->validateSlug($data);

        return $this->translatableHandleRecordCreation($data);
    }

    /** @param array<string, mixed> $data */
    private function validateSlug(array $data): void
    {
        try {
            TagForm::assertUniqueSlug($data, locale: $this->activeLocale);
        } catch (ValidationException $validationException) {
            $this->js("document.getElementById('tag-slug')?.focus()");
            throw ValidationException::withMessages(['data.slug' => $validationException->errors()['slug']]);
        }
    }
}
