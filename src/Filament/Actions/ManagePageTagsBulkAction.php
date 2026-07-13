<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Actions;

use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Page;
use Capell\Tags\Actions\ManagePageTagsAction;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Closure;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TagsInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

final class ManagePageTagsBulkAction extends BulkAction
{
    public static function make(?string $name = null): static
    {
        return parent::make($name ?? 'managePageTags')
            ->label(__('capell-tags::bulk.manage_pages.label'))
            ->icon(Heroicon::OutlinedTag)
            ->modalIcon(Heroicon::OutlinedTag)
            ->modalHeading(__('capell-tags::bulk.manage_pages.modal.heading'))
            ->modalSubmitActionLabel(__('capell-tags::bulk.manage_pages.modal.actions.save.label'))
            ->modalWidth(Width::Large)
            ->authorizeIndividualRecords('update')
            ->schema([
                TagsInput::make('tagsToAttach')
                    ->label(__('capell-tags::bulk.manage_pages.modal.form.tags_to_attach.label'))
                    ->suggestions(static fn (): array => self::tagSuggestions())
                    ->nestedRecursiveRules(['string'])
                    ->requiredWithout('tagsToDetach'),
                TagsInput::make('tagsToDetach')
                    ->label(__('capell-tags::bulk.manage_pages.modal.form.tags_to_detach.label'))
                    ->suggestions(static fn (): array => self::tagSuggestions())
                    ->nestedRecursiveRules(['string'])
                    ->requiredWithout('tagsToAttach')
                    ->rules([
                        static fn (Get $get): Closure => self::preventAttachDetachConflicts($get),
                    ]),
            ])
            ->action(function (EloquentCollection $records, array $data): void {
                $updated = 0;

                $records
                    ->filter(static fn (Model $record): bool => $record instanceof Page)
                    ->each(function (Page $page) use ($data, &$updated): void {
                        Gate::authorize('update', $page);

                        ManagePageTagsAction::run(
                            page: $page,
                            tagsToAttach: self::tagNames(is_array($data['tagsToAttach'] ?? null) ? $data['tagsToAttach'] : []),
                            tagsToDetach: self::tagNames(is_array($data['tagsToDetach'] ?? null) ? $data['tagsToDetach'] : []),
                            type: TagTypeEnum::Page->value,
                        );

                        $updated++;
                    });

                Notification::make('capell-tags-page-tags-updated')
                    ->title(__('capell-tags::bulk.manage_pages.notifications.updated.title', [
                        'count' => Number::format($updated),
                    ]))
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    /**
     * @return array<int, string>
     */
    private static function tagSuggestions(): array
    {
        return Tag::query()
            ->where('type', TagTypeEnum::Page->value)
            ->where(function (Builder $query): void {
                $query->whereNull('site_id');

                $actor = auth()->user();

                if (! $actor instanceof Authenticatable) {
                    return;
                }

                if (SiteScope::isGlobalActor($actor)) {
                    $query->orWhereNotNull('site_id');

                    return;
                }

                if (! method_exists($actor, 'getAssignedSiteIds')) {
                    return;
                }

                $assignedSiteIds = call_user_func([$actor, 'getAssignedSiteIds']);

                if (! $assignedSiteIds instanceof SupportCollection) {
                    return;
                }

                if ($assignedSiteIds->isNotEmpty()) {
                    $query->orWhereIn('site_id', $assignedSiteIds);
                }
            })
            ->get()
            ->map(static fn (Tag $tag): string => self::tagName($tag))
            ->filter(static fn (string $name): bool => $name !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, mixed>  $tagNames
     * @return list<string>
     */
    private static function tagNames(array $tagNames): array
    {
        return array_values(collect($tagNames)
            ->filter(static fn (mixed $tagName): bool => is_string($tagName) && trim($tagName) !== '')
            ->map(static fn (mixed $tagName): string => trim((string) $tagName))
            ->values()
            ->all());
    }

    private static function preventAttachDetachConflicts(Get $get): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($get): void {
            $tagsToAttach = $get('tagsToAttach');
            $tagsToAttachSlugs = collect(is_array($tagsToAttach) ? $tagsToAttach : [])
                ->filter(static fn (mixed $tag): bool => is_string($tag))
                ->map(static fn (mixed $tag): string => Str::slug((string) $tag))
                ->all();

            $conflictingTags = collect(is_array($value) ? $value : [])
                ->filter(static fn (mixed $tag): bool => is_string($tag))
                ->filter(static fn (mixed $tag): bool => in_array(Str::slug((string) $tag), $tagsToAttachSlugs, true))
                ->values();

            if ($conflictingTags->isEmpty()) {
                return;
            }

            $fail(__('capell-tags::bulk.manage_pages.modal.form.tags_to_detach.validation.attached_and_detached', [
                'tags' => $conflictingTags->implode(', '),
            ]));
        };
    }

    private static function tagName(Tag $tag): string
    {
        $name = $tag->getTranslation('name', app()->getLocale(), false);

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $rawName = $tag->getAttribute('name');
        $fallbackName = is_array($rawName) ? reset($rawName) : null;

        return is_scalar($fallbackName) ? (string) $fallbackName : '';
    }
}
