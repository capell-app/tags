<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Data\ResolvedTagSlugData;
use Capell\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static ResolvedTagSlugData|null run(string $slug, int $siteId, string $locale, ?string $type = null)
 */
final class ResolveTagBySlugAction
{
    use AsObject;

    public function handle(string $slug, int $siteId, string $locale, ?string $type = null): ?ResolvedTagSlugData
    {
        $tag = $this->baseQuery($siteId, $type)
            ->where('slug->' . $locale, $slug)
            ->first();

        if (! $tag instanceof Tag) {
            $tag = $this->baseQuery($siteId, $type)
                ->whereJsonContains('merged_slug_aliases->' . $locale, $slug)
                ->first();
        }

        if (! $tag instanceof Tag) {
            return null;
        }

        $canonicalSlug = $tag->getTranslation('slug', $locale, false);

        return new ResolvedTagSlugData(
            tag: $tag,
            requestedSlug: $slug,
            canonicalSlug: is_string($canonicalSlug) && $canonicalSlug !== '' ? $canonicalSlug : $slug,
        );
    }

    /** @return Builder<Tag> */
    private function baseQuery(int $siteId, ?string $type): Builder
    {
        return Tag::query()
            ->enabled()
            ->when($type !== null, static fn (Builder $query): Builder => $query->where('type', $type))
            ->where(
                static fn (Builder $query): Builder => $query->where('site_id', $siteId)->orWhereNull('site_id'),
            )
            ->orderByRaw('CASE WHEN site_id = ? THEN 0 ELSE 1 END', [$siteId]);
    }
}
