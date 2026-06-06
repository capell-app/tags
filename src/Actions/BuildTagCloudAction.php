<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Data\TagCloudItemData;
use Capell\Tags\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildTagCloudAction
{
    use AsObject;

    /**
     * @return Collection<int, TagCloudItemData>
     */
    public function handle(?int $siteId = null, ?string $type = null, int $limit = 30, int $buckets = 5): Collection
    {
        $limit = max(1, $limit);
        $buckets = max(1, $buckets);

        /** @var Collection<int, Tag> $tags */
        $tags = Tag::query()
            ->enabled()
            ->when($siteId !== null, static function (Builder $query) use ($siteId): void {
                $query->where(static function (Builder $siteQuery) use ($siteId): void {
                    $siteQuery->whereNull('site_id')
                        ->orWhere('site_id', $siteId);
                });
            })
            ->when($type !== null, static fn (Builder $query): Builder => $query->where('type', $type))
            ->withCount('taggables')
            ->orderByDesc('taggables_count')
            ->ordered()
            ->limit($limit)
            ->get();

        $maximum = max(1, (int) $tags->max('taggables_count'));

        return $tags
            ->map(static function (Tag $tag) use ($maximum, $buckets): TagCloudItemData {
                $usageCount = (int) $tag->taggables_count;
                $weight = $usageCount === 0 ? 1 : (int) ceil(($usageCount / $maximum) * $buckets);

                return new TagCloudItemData(
                    tag: $tag,
                    usageCount: $usageCount,
                    weight: max(1, min($buckets, $weight)),
                );
            })
            ->sortBy(static fn (TagCloudItemData $item): string => (string) $item->tag->name)
            ->values();
    }
}
