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
use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Language;
use Capell\Tags\Actions\BuildTagUsageAction;
use Capell\Tags\Actions\MergeTagsAction;
use Capell\Tags\Actions\PreviewTagMergeAction;
use Capell\Tags\Data\TagMergePreviewData;
use Capell\Tags\Filament\Resources\Tags\Pages\ListTags;
use Capell\Tags\Models\Tag;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

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
                self::mergeTagsBulkAction(),
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
                ->action(Action::make('tagUsage')
                    ->label(__('capell-tags::table.total_taggables'))
                    ->authorize(fn (Tag $record): bool => Gate::allows('view', $record))
                    ->modalSubmitAction(false)
                    ->modalContent(fn (Tag $record): ViewContract => view('capell-tags::admin.usage', [
                        'groups' => (new BuildTagUsageAction)->handle([$record], self::actor()),
                    ])))
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

    private static function mergeTagsBulkAction(): BulkAction
    {
        return BulkAction::make('mergeTags')
            ->label(__('capell-tags::generic.merge_tags'))
            ->icon('heroicon-o-arrows-right-left')
            ->requiresConfirmation()
            ->modalHeading(__('capell-tags::generic.merge_tags'))
            ->modalDescription(__('capell-tags::generic.merge_tags_description'))
            ->visible(static fn (): bool => Gate::allows('deleteAny', Tag::class))
            ->authorize(static fn (): bool => Gate::allows('deleteAny', Tag::class))
            ->authorizeIndividualRecords('delete')
            ->mountUsing(function (Schema $schema, ListTags $livewire): void {
                $livewire->mergeReviewFingerprint = null;
                $schema->fill();
            })
            ->modalSubmitActionLabel(__('capell-tags::generic.merge_apply'))
            ->steps([
                Step::make(__('capell-tags::generic.merge_choose'))
                    ->schema([
                        Select::make('target_tag_id')
                            ->label(__('capell-tags::generic.merge_tags_target'))
                            ->helperText(__('capell-tags::generic.merge_tags_incompatible'))
                            ->options(static fn (ListTags $livewire): array => self::tagOptions(self::selectedTags($livewire)))
                            ->live()
                            ->afterStateUpdated(function (ListTags $livewire): void {
                                $livewire->mergeReviewFingerprint = null;
                            })
                            ->searchable()
                            ->required(),
                    ])
                    ->afterValidation(function (Get $get, ListTags $livewire): void {
                        $records = self::selectedTags($livewire);
                        $livewire->mergeReviewFingerprint = null;
                        $target = Tag::query()->findOrFail(self::integerValue($get('target_tag_id')));
                        $sources = $records->reject(fn (Tag $tag): bool => $tag->is($target))->values();
                        $livewire->mergeReviewFingerprint = (new PreviewTagMergeAction)->handle($target, array_values($sources->all()), self::actor())->fingerprint;
                    }),
                Step::make(__('capell-tags::generic.merge_review'))
                    ->schema([
                        View::make('capell-tags::admin.merge-preview')
                            ->viewData(function (Get $get, ListTags $livewire): array {
                                if ($livewire->mergeReviewFingerprint === null) {
                                    return ['preview' => null];
                                }

                                $records = self::selectedTags($livewire);
                                $target = Tag::query()->find(self::integerValue($get('target_tag_id')));

                                try {
                                    $preview = $target instanceof Tag ? (new PreviewTagMergeAction)->handle($target, array_values($records->reject(fn (Tag $tag): bool => $tag->is($target))->all()), self::actor()) : null;
                                } catch (ValidationException|ModelNotFoundException) {
                                    $preview = null;
                                }

                                if (! $preview instanceof TagMergePreviewData || ! hash_equals($livewire->mergeReviewFingerprint, $preview->fingerprint)) {
                                    $livewire->mergeReviewFingerprint = null;

                                    return ['preview' => null];
                                }

                                return ['preview' => $preview];
                            }),
                    ]),
            ])
            ->action(function (array $data, EloquentCollection $records, ListTags $livewire): void {
                if ($livewire->mergeReviewFingerprint === null) {
                    throw ValidationException::withMessages(['target_tag_id' => __('capell-tags::generic.review_stale')]);
                }

                $targetTag = Tag::query()->findOrFail(self::integerValue($data['target_tag_id'] ?? null));
                Gate::authorize('update', $targetTag);
                $sourceTags = self::tagRecords($records)->reject(fn (Tag $tag): bool => $tag->is($targetTag))->values();

                $sourceTags->each(static function (Tag $sourceTag): void {
                    Gate::authorize('delete', $sourceTag);
                });

                $mergedCount = MergeTagsAction::run($targetTag, $sourceTags, self::actor(), $livewire->mergeReviewFingerprint);

                $livewire->mergeReviewFingerprint = null;

                Notification::make('capell-tags-merged')
                    ->title(__('capell-tags::generic.merge_tags_complete', ['count' => $mergedCount]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @param  EloquentCollection<int, Tag>  $selectedTags
     * @return array<int, string>
     */
    private static function tagOptions(EloquentCollection $selectedTags): array
    {
        $scopes = $selectedTags
            ->map(static fn (Tag $tag): string => sprintf('%s:%s', $tag->type ?? '', $tag->site_id === null ? 'global' : (string) $tag->site_id))
            ->unique();

        if ($scopes->count() !== 1) {
            return [];
        }

        $selectedTag = $selectedTags->first();

        if (! $selectedTag instanceof Tag) {
            return [];
        }

        return self::scopeTagOptionsToActor(Tag::query())
            ->where('type', $selectedTag->type)
            ->where('site_id', $selectedTag->site_id)
            ->enabled()
            ->ordered()
            ->limit(250)
            ->get()
            ->mapWithKeys(static fn (Tag $tag): array => [
                self::integerValue($tag->getKey()) => sprintf('%s #%d', self::tagName($tag), self::integerValue($tag->getKey())),
            ])
            ->all();
    }

    /**
     * @param  Builder<Tag>  $query
     * @return Builder<Tag>
     */
    private static function scopeTagOptionsToActor(Builder $query): Builder
    {
        $actor = self::actor();

        if (SiteScope::isGlobalActor($actor)) {
            return $query;
        }

        $assignedSiteIds = $actor->getAssignedSiteIds();

        return $query->where(function (Builder $query) use ($assignedSiteIds): void {
            $query->whereNull('site_id');

            if ($assignedSiteIds->isNotEmpty()) {
                $query->orWhereIn('site_id', $assignedSiteIds);
            }
        });
    }

    /** @return EloquentCollection<int, Tag> */
    private static function selectedTags(ListTags $livewire): EloquentCollection
    {
        $tags = [];
        foreach ($livewire->getSelectedTableRecords() as $record) {
            if ($record instanceof Tag) {
                $tags[] = $record;
            }
        }

        return new EloquentCollection($tags);
    }

    private static function actor(): Authenticatable
    {
        return auth()->user() ?? throw new AuthorizationException;
    }

    private static function integerValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private static function tagName(Tag $tag): string
    {
        $name = $tag->getTranslation('name', app()->getLocale(), false);

        return is_string($name) ? $name : '';
    }

    /**
     * @param  EloquentCollection<array-key, Model>  $records
     * @return EloquentCollection<int, Tag>
     */
    private static function tagRecords(EloquentCollection $records): EloquentCollection
    {
        return EloquentCollection::make($records)
            ->filter(static fn (mixed $record): bool => $record instanceof Tag)
            ->values();
    }
}
