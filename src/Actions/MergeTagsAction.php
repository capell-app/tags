<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(Tag $targetTag, iterable<int, Tag> $sourceTags)
 */
final class MergeTagsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<int, Tag>  $sourceTags
     */
    public function handle(Tag $targetTag, iterable $sourceTags): int
    {
        $sources = EloquentCollection::make($sourceTags)
            ->filter(fn (Tag $sourceTag): bool => $this->tagKey($sourceTag) !== $this->tagKey($targetTag))
            ->values();

        if ($sources->isEmpty()) {
            return 0;
        }

        $this->assertCompatibleSources($targetTag, $sources);

        return DB::transaction(function () use ($targetTag, $sources): int {
            $merged = 0;

            $this->preserveSlugAliases($targetTag, $sources);

            foreach ($sources as $sourceTag) {
                $this->moveTaggables($sourceTag, $targetTag);
                $sourceTag->delete();
                $merged++;
            }

            return $merged;
        });
    }

    /**
     * @param  EloquentCollection<int, Tag>  $sourceTags
     */
    private function preserveSlugAliases(Tag $targetTag, EloquentCollection $sourceTags): void
    {
        $aliases = $this->normalizeAliases($targetTag->getAttribute('merged_slug_aliases'));

        foreach ($sourceTags as $sourceTag) {
            $aliases = $this->mergeAliases($aliases, $this->normalizeAliases($sourceTag->getAttribute('merged_slug_aliases')));

            foreach ($sourceTag->getTranslations('slug') as $locale => $slug) {
                if (! is_string($locale) || ! is_string($slug) || trim($slug) === '') {
                    continue;
                }

                $aliases[$locale][] = trim($slug);
            }
        }

        foreach ($targetTag->getTranslations('slug') as $locale => $canonicalSlug) {
            if (! is_string($locale) || ! is_string($canonicalSlug)) {
                continue;
            }

            $aliases[$locale] = array_values(array_filter(
                $aliases[$locale] ?? [],
                static fn (string $alias): bool => $alias !== $canonicalSlug,
            ));
        }

        foreach ($aliases as $locale => $slugs) {
            $aliases[$locale] = array_values(array_unique($slugs));
            sort($aliases[$locale]);

            if ($aliases[$locale] === []) {
                unset($aliases[$locale]);
            }
        }

        ksort($aliases);

        $targetTag->forceFill(['merged_slug_aliases' => $aliases])->save();
    }

    /**
     * @param  array<string, list<string>>  $aliases
     * @param  array<string, list<string>>  $additionalAliases
     * @return array<string, list<string>>
     */
    private function mergeAliases(array $aliases, array $additionalAliases): array
    {
        foreach ($additionalAliases as $locale => $slugs) {
            $aliases[$locale] = [...($aliases[$locale] ?? []), ...$slugs];
        }

        return $aliases;
    }

    /** @return array<string, list<string>> */
    private function normalizeAliases(mixed $aliases): array
    {
        if (! is_array($aliases)) {
            return [];
        }

        $normalized = [];

        foreach ($aliases as $locale => $slugs) {
            if (! is_string($locale) || ! is_array($slugs)) {
                continue;
            }

            $normalized[$locale] = array_values(array_filter(
                $slugs,
                static fn (mixed $slug): bool => is_string($slug) && trim($slug) !== '',
            ));
        }

        return $normalized;
    }

    /**
     * @param  EloquentCollection<int, Tag>  $sourceTags
     */
    private function assertCompatibleSources(Tag $targetTag, EloquentCollection $sourceTags): void
    {
        $incompatible = $sourceTags->contains(
            static fn (Tag $sourceTag): bool => $sourceTag->type !== $targetTag->type
                || $sourceTag->site_id !== $targetTag->site_id,
        );

        if ($incompatible) {
            throw new InvalidArgumentException((string) __('capell-tags::generic.merge_tags_incompatible'));
        }
    }

    private function moveTaggables(Tag $sourceTag, Tag $targetTag): void
    {
        Taggable::query()
            ->where('tag_id', $sourceTag->getKey())
            ->get()
            ->each(function (Taggable $taggable) use ($targetTag): void {
                $duplicate = Taggable::query()
                    ->where('tag_id', $targetTag->getKey())
                    ->where('taggable_type', $taggable->taggable_type)
                    ->where('taggable_id', $taggable->taggable_id)
                    ->where('workspace_id', $taggable->workspace_id)
                    ->exists();

                if ($duplicate) {
                    $taggable->delete();

                    return;
                }

                $taggable->forceFill([
                    'tag_id' => $targetTag->getKey(),
                ])->save();
            });
    }

    private function tagKey(Tag $tag): int
    {
        $key = $tag->getKey();

        return is_numeric($key) ? (int) $key : 0;
    }
}
