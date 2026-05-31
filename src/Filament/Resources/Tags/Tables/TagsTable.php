<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Resources\Tags\Tables;

use Capell\Admin\Enums\FilamentColorEnum;
use Capell\Admin\Filament\Components\Tables\Actions\EditAction;
use Capell\Admin\Filament\Components\Tables\Actions\ReplicateAction;
use Capell\Admin\Filament\Components\Tables\Columns\DateColumn;
use Capell\Admin\Filament\Components\Tables\Columns\IdentifierColumn;
use Capell\Admin\Filament\Components\Tables\Columns\NameColumn;
use Capell\Admin\Filament\Components\Tables\Columns\SiteColumn;
use Capell\Admin\Filament\Components\Tables\Columns\StatusIconColumn;
use Capell\Admin\Filament\Components\Tables\Filters\StatusFilter;
use Capell\Admin\Filament\Contracts\TableConfigurator;
use Capell\Core\Models\Language;
use Capell\Tags\Models\Tag;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TagsTable implements TableConfigurator
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['site'])
                    ->select('*')
                    ->withTranslatedLocales('name'),
            )
            ->defaultSort('name')
            ->columns(static::getTableColumns())
            ->filters([
                SelectFilter::make('site_id')
                    ->label(__('capell-admin::form.site'))
                    ->relationship(name: 'site', titleAttribute: 'name'),
                TernaryFilter::make('featured')
                    ->label(__('capell-tags::table.featured'))
                    ->trueLabel(__('capell-admin::generic.yes'))
                    ->falseLabel(__('capell-admin::generic.no'))
                    ->placeholder(__('capell-admin::generic.all')),
                StatusFilter::make('status'),
            ])
            ->recordActions([
                EditAction::make(),
                ActionGroup::make([
                    ReplicateAction::make(),
                    DeleteAction::make(),
                ])
                    ->color('gray'),
            ])
            ->toolbarActions([
                DeleteBulkAction::make(),
            ]);
    }

    /**
     * @return array<array-key, mixed>
     */
    protected static function getTableColumns(): array
    {
        return [
            IdentifierColumn::make('id'),
            NameColumn::make('name')
                ->state(fn (Tag $record, mixed $livewire): string => self::translatedAttributeForActiveLocale($record, 'name', $livewire))
                ->searchable(query: self::applyActiveLocaleNameSearch(...)),
            TextColumn::make('slug')
                ->label(__('capell-tags::table.slug'))
                ->state(fn (Tag $record, mixed $livewire): string => self::translatedAttributeForActiveLocale($record, 'slug', $livewire))
                ->searchable(query: self::applyActiveLocaleSlugSearch(...))
                ->sortable()
                ->color(FilamentColorEnum::LightGray->value)
                ->toggleable(),
            TextColumn::make('translated_locales')
                ->label(__('capell-admin::table.languages'))
                ->toggleable(isToggledHiddenByDefault: true)
                ->view('capell-admin::components.tables.columns.locale-flags'),
            SiteColumn::make('site.name'),
            TextColumn::make('taggables_count')
                ->label(__('capell-tags::table.total_taggables'))
                ->counts('taggables')
                ->sortable()
                ->alignRight()
                ->numeric()
                ->toggleable(),
            ToggleColumn::make('featured')
                ->label(__('capell-tags::table.featured'))
                ->alignCenter()
                ->toggleable(),
            StatusIconColumn::make('status'),
            DateColumn::make('created_at'),
            DateColumn::make('updated_at'),
        ];
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    protected static function applyActiveLocaleNameSearch(Builder $query, string $search, mixed $livewire): Builder
    {
        return self::applyActiveLocaleSearch($query, 'name', $search, $livewire);
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    protected static function applyActiveLocaleSlugSearch(Builder $query, string $search, mixed $livewire): Builder
    {
        return self::applyActiveLocaleSearch($query, 'slug', $search, $livewire);
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    protected static function applyActiveLocaleSearch(Builder $query, string $column, string $search, mixed $livewire): Builder
    {
        if ($search === '' || $search === '0') {
            return $query;
        }

        return $query->whereJsonContainsLocale(
            $column,
            self::activeLocale($livewire),
            sprintf('%%%s%%', $search),
            'like',
        );
    }

    protected static function translatedAttributeForActiveLocale(Tag $record, string $attribute, mixed $livewire): string
    {
        $value = $record->getTranslation($attribute, self::activeLocale($livewire), false);

        if (filled($value)) {
            return (string) $value;
        }

        return (string) $record->getAttribute($attribute);
    }

    protected static function activeLocale(mixed $livewire): string
    {
        $locale = method_exists($livewire, 'getActiveTableLocale')
            ? $livewire->getActiveTableLocale()
            : null;

        if (is_string($locale) && $locale !== '') {
            return $locale;
        }

        return Language::query()->default()->value('code') ?? app()->getLocale();
    }
}
