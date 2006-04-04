<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Tags\Enums\TagTypeEnum;
use Capell\Tags\Models\Tag;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static void run(Page $page, array<int, mixed> $tagsToAttach = [], array<int, mixed> $tagsToDetach = [], ?string $type = null, ?Authenticatable $actor = null)
 */
final class ManagePageTagsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<int, mixed>  $tagsToAttach
     * @param  array<int, mixed>  $tagsToDetach
     */
    public function handle(Page $page, array $tagsToAttach = [], array $tagsToDetach = [], ?string $type = null, ?Authenticatable $actor = null): void
    {
        $type ??= TagTypeEnum::Page->value;

        $attachNames = $this->normaliseTagNames($tagsToAttach);
        $detachNames = $this->normaliseTagNames($tagsToDetach);

        if ($attachNames === [] && $detachNames === []) {
            return;
        }

        DB::transaction(function () use ($page, $attachNames, $detachNames, $type, $actor): void {
            Gate::forUser($this->actor($actor))->authorize('update', $page);

            $tagIdsToAttach = $this->resolveTagIdsForAttaching($page, $attachNames, $type);
            $tagIdsToDetach = $this->resolveTagIdsForDetaching($page, $detachNames, $type);
            $relation = $this->tagsRelation($page);

            if ($tagIdsToAttach !== []) {
                $relation->syncWithoutDetaching($tagIdsToAttach);
            }

            if ($tagIdsToDetach !== []) {
                $relation->detach($tagIdsToDetach);
            }

            $page->unsetRelation('tags');
        });
    }

    /**
     * @param  array<int, mixed>  $tagNames
     * @return list<string>
     */
    private function normaliseTagNames(array $tagNames): array
    {
        return array_values(collect($tagNames)
            ->filter(static fn (mixed $tagName): bool => is_string($tagName) && trim($tagName) !== '')
            ->map(static fn (mixed $tagName): string => trim((string) $tagName))
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @param  list<string>  $tagNames
     * @return list<int>
     */
    private function resolveTagIdsForAttaching(Page $page, array $tagNames, string $type): array
    {
        if ($tagNames === []) {
            return [];
        }

        return array_values($this->pageLocales($page)
            ->flatMap(fn (string $locale): Collection => collect(Tag::findOrCreateForSite(
                values: $tagNames,
                type: $type,
                locale: $locale,
                siteId: $this->pageSiteId($page),
            )))
            ->pluck('id')
            ->filter(static fn (mixed $tagId): bool => is_numeric($tagId))
            ->map(static fn (mixed $tagId): int => (int) $tagId)
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @param  list<string>  $tagNames
     * @return list<int>
     */
    private function resolveTagIdsForDetaching(Page $page, array $tagNames, string $type): array
    {
        if ($tagNames === []) {
            return [];
        }

        $locales = $this->pageLocales($page);
        $siteId = $this->pageSiteId($page);

        return array_values(Tag::query()
            ->where('type', $type)
            ->where(function (Builder $query) use ($siteId): void {
                $query->whereNull('site_id');

                if ($siteId !== null) {
                    $query->orWhere('site_id', $siteId);
                }
            })
            ->where(function (Builder $query) use ($locales, $tagNames): void {
                foreach ($locales as $locale) {
                    $query
                        ->orWhereIn('name->' . $locale, $tagNames)
                        ->orWhereIn('slug->' . $locale, $tagNames);
                }
            })
            ->pluck('id')
            ->filter(static fn (mixed $tagId): bool => is_numeric($tagId))
            ->map(static fn (mixed $tagId): int => (int) $tagId)
            ->unique()
            ->values()
            ->all());
    }

    /**
     * @return Collection<int, string>
     */
    private function pageLocales(Page $page): Collection
    {
        $page->loadMissing('languages');

        $locales = array_values(array_filter(
            $page->languages->map(static fn (Language $language): string => $language->code)->all(),
            static fn (string $locale): bool => $locale !== '',
        ));

        return collect(array_values(array_unique($locales === [] ? [app()->getLocale()] : $locales)));
    }

    private function pageSiteId(Page $page): ?int
    {
        $siteId = $page->getAttribute('site_id');

        return is_numeric($siteId) ? (int) $siteId : null;
    }

    private function actor(?Authenticatable $actor): Authenticatable
    {
        $actor ??= auth()->user();

        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        return $actor;
    }

    /**
     * @return MorphToMany<Tag, Page>
     */
    private function tagsRelation(Page $page): MorphToMany
    {
        /** @var MorphToMany<Tag, Page> $relation */
        $relation = $page->morphToMany(Tag::class, 'taggable', 'taggables');

        return $relation;
    }
}
