<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Data\RelatedTaggableData;
use Capell\Tags\Models\Taggable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

final class FindRelatedTaggablesAction
{
    use AsObject;

    /**
     * @param  class-string<Model>  $relatedModelClass
     * @return Collection<int, RelatedTaggableData>
     */
    public function handle(Model $record, string $relatedModelClass, ?string $tagType = null, int $limit = 6): Collection
    {
        $limit = max(1, $limit);
        $sourceMorphType = $record->getMorphClass();
        $targetMorphType = (new $relatedModelClass)->getMorphClass();

        $tagIds = Taggable::query()
            ->where('taggable_type', $sourceMorphType)
            ->where('taggable_id', $record->getKey())
            ->when($tagType !== null, static function (Builder $query) use ($tagType): void {
                $query->whereHas('tag', static function (Builder $tagQuery) use ($tagType): void {
                    $tagQuery->where('type', $tagType);
                });
            })
            ->pluck('tag_id');

        if ($tagIds->isEmpty()) {
            return new Collection;
        }

        $matches = Taggable::query()
            ->select('taggable_id', DB::raw('COUNT(DISTINCT tag_id) as shared_tag_count'))
            ->where('taggable_type', $targetMorphType)
            ->whereIn('tag_id', $tagIds)
            ->when(
                $sourceMorphType === $targetMorphType,
                static fn (Builder $query): Builder => $query->where('taggable_id', '!=', $record->getKey()),
            )
            ->groupBy('taggable_id')
            ->orderByDesc('shared_tag_count')
            ->orderBy('taggable_id')
            ->limit($limit)
            ->get();

        $records = $relatedModelClass::query()
            ->whereKey($matches->pluck('taggable_id')->all())
            ->get()
            ->keyBy(static fn (Model $relatedRecord): string => (string) $relatedRecord->getKey());

        return $matches
            ->map(static function (Taggable $match) use ($records): ?RelatedTaggableData {
                $record = $records->get((string) $match->taggable_id);

                if (! $record instanceof Model) {
                    return null;
                }

                return new RelatedTaggableData(
                    record: $record,
                    sharedTagCount: (int) $match->getAttribute('shared_tag_count'),
                );
            })
            ->filter()
            ->values();
    }
}
