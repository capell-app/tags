<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Resources\Tags\Schemas;

use BackedEnum;
use Capell\Admin\Data\Configurators\ConfiguratorContextData;
use Capell\Admin\Filament\Components\Forms\NameInput;
use Capell\Admin\Filament\Components\Forms\SiteSelect;
use Capell\Admin\Filament\Components\Forms\StatusToggle;
use Capell\Admin\Filament\Contracts\FormConfigurator;
use Capell\Core\Support\Slug\SlugGenerator;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TagForm implements FormConfigurator
{
    public static function configure(Schema $configurator, ?ConfiguratorContextData $context = null): Schema
    {
        return $configurator->components(self::getFormSchema($configurator))->columns();
    }

    public static function typeSelect(): Select
    {
        return Select::make('type')
            ->label(__('capell-admin::form.type'))
            ->options(TagTypeEnum::class)
            ->default(TagTypeEnum::Page->value)
            ->required()
            ->native(false);
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public static function assertUniqueSlug(array $data, ?Model $record = null, ?string $locale = null): void
    {
        $locale ??= app()->getLocale();
        $slug = self::localizedValue($data['slug'] ?? null, $locale);

        if ($slug === null || $slug === '') {
            return;
        }

        $type = $data['type'] ?? null;
        $typeValue = $type instanceof BackedEnum ? $type->value : $type;
        $siteId = $data['site_id'] ?? null;

        $query = Tag::query()
            ->where('type', is_string($typeValue) ? $typeValue : null)
            ->where('site_id', is_numeric($siteId) ? (int) $siteId : null);

        if ($record instanceof Tag && $record->getKey() !== null) {
            $query->whereKeyNot($record->getKey());
        }

        $duplicateExists = $query
            ->get()
            ->contains(static fn (Tag $tag): bool => $tag->getTranslation('slug', $locale, false) === $slug);

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'slug' => __('capell-tags::form.slug_unique'),
            ]);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    protected static function getFormSchema(Schema $configurator): array
    {
        return [
            Section::make()
                ->columns()
                ->columnSpanFull()
                ->schema([
                    NameInput::make('name')
                        ->afterStateUpdatedJs(function (string $operation): string {
                            if (! in_array($operation, ['create', 'createOption', 'replicate'], true)) {
                                return '';
                            }

                            return SlugGenerator::slugifyState("\$state ?? ''", 'slug');
                        }),

                    TextInput::make('slug')
                        ->label(__('capell-tags::form.slug'))
                        ->alphaDash()
                        ->required()
                        ->maxLength(128)
                        ->required(),

                    self::typeSelect(),

                    SiteSelect::make('site_id'),

                    Grid::make()
                        ->columnSpanFull()
                        ->schema([
                            Checkbox::make('featured')
                                ->label(__('capell-tags::form.featured'))
                                ->helperText(__('capell-admin::generic.featured_hint')),

                            StatusToggle::make('status'),
                        ]),
                ])
                ->contained(in_array($configurator->getOperation(), ['create', 'edit'], true)),
        ];
    }

    private static function localizedValue(mixed $value, string $locale): ?string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value) && is_string($value[$locale] ?? null)) {
            return trim($value[$locale]);
        }

        return null;
    }
}
