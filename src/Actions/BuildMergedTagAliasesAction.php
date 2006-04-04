<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

final class BuildMergedTagAliasesAction
{
    /**
     * @param  EloquentCollection<int, Tag>  $sourceTags
     * @return array<string, list<string>>
     */
    public function handle(Tag $targetTag, EloquentCollection $sourceTags): array
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

        return $aliases;
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
}
