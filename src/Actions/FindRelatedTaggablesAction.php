<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Data\RelatedTaggableData;
use Capell\Tags\Models\Taggable;
use Capell\Tags\Support\TagModelRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static Collection<int, RelatedTaggableData> run(Model $record, Builder<covariant Model> $relatedQuery, ?string $tagType = null, int $limit = 6)
 */
final class FindRelatedTaggablesAction
{
    use AsFake;
    use AsObject;

    /**
     * The caller owns the related query's publication, visibility, and site
     * constraints. This action only ranks records that remain in that query.
     *
     * @param  Builder<covariant Model>  $relatedQuery
     * @return Collection<int, RelatedTaggableData>
     */
    public function handle(Model $record, Builder $relatedQuery, ?string $tagType = null, int $limit = 6): Collection
    {
        $limit = max(1, $limit);
        $relatedModel = $relatedQuery->getModel();
        $relatedModelClass = $relatedModel::class;

        $this->assertRegisteredTaggable($record::class);
        $this->assertRegisteredTaggable($relatedModelClass);

        $sourceMorphType = $record->getMorphClass();
        $targetMorphType = $relatedModel->getMorphClass();
        $siteId = $this->siteId($record);

        $tagIds = Taggable::query()
            ->where('taggable_type', $sourceMorphType)
            ->where('taggable_id', $record->getKey())
            ->whereHas('tag', static function (Builder $tagQuery) use ($tagType, $siteId): void {
                $tagQuery
                    ->enabled()
                    ->when($tagType !== null, static fn (Builder $query): Builder => $query->where('type', $tagType))
                    ->where(function (Builder $query) use ($siteId): void {
                        $query->whereNull('site_id');

                        if ($siteId !== null) {
                            $query->orWhere('site_id', $siteId);
                        }
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
            ->whereIn(
                'taggable_id',
                (clone $relatedQuery)->select($relatedModel->getQualifiedKeyName()),
            )
            ->when(
                $sourceMorphType === $targetMorphType,
                static fn (Builder $query): Builder => $query->where('taggable_id', '!=', $record->getKey()),
            )
            ->groupBy('taggable_id')
            ->orderByDesc('shared_tag_count')
            ->orderBy('taggable_id')
            ->limit($limit)
            ->get();

        $records = (clone $relatedQuery)
            ->whereKey($matches->pluck('taggable_id')->all())
            ->get()
            ->keyBy(fn (Model $relatedRecord): string => $this->modelKey($relatedRecord));

        return $matches
            ->map(function (Taggable $match) use ($records): ?RelatedTaggableData {
                $record = $records->get($this->stringValue($match->taggable_id));

                if (! $record instanceof Model) {
                    return null;
                }

                return new RelatedTaggableData(
                    record: $record,
                    sharedTagCount: $this->integerValue($match->getAttribute('shared_tag_count')),
                );
            })
            ->filter()
            ->values();
    }

    /** @param class-string<Model> $modelClass */
    private function assertRegisteredTaggable(string $modelClass): void
    {
        if (! TagModelRegistrar::isTaggableRegistered($modelClass)) {
            throw new InvalidArgumentException(sprintf(
                'Related taggable model [%s] is not registered with the Tags package.',
                $modelClass,
            ));
        }
    }

    private function siteId(Model $record): ?int
    {
        $siteId = $record->getAttribute('site_id');

        return is_numeric($siteId) ? (int) $siteId : null;
    }

    private function integerValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function modelKey(Model $model): string
    {
        return $this->stringValue($model->getKey());
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
