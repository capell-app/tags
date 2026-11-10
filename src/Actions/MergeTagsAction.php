<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(Tag $targetTag, iterable<int, Tag> $sourceTags, Authenticatable $actor, string $reviewFingerprint)
 */
final class MergeTagsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<int, Tag>  $sourceTags
     */
    public function handle(Tag $targetTag, iterable $sourceTags, Authenticatable $actor, string $reviewFingerprint): int
    {
        $sources = EloquentCollection::make($sourceTags)
            ->unique('id')
            ->values();

        if ($sources->isEmpty() || $sources->contains(fn (Tag $source): bool => $source->is($targetTag))) {
            throw new InvalidArgumentException((string) __('capell-tags::generic.merge_sources_required'));
        }

        return DB::transaction(function () use ($targetTag, $sources, $actor, $reviewFingerprint): int {
            $locked = Tag::query()->whereKey([$targetTag->id, ...$sources->modelKeys()])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $targetTag = $locked->get($targetTag->id) ?? throw new InvalidArgumentException((string) __('capell-tags::generic.review_stale'));
            $sources = $sources->map(fn (Tag $tag): Tag => $locked->get($tag->id) ?? throw new InvalidArgumentException((string) __('capell-tags::generic.review_stale')));
            $gate = Gate::forUser($actor);
            $gate->authorize('update', $targetTag);

            $sources->each(static function (Tag $sourceTag) use ($gate): void {
                $gate->authorize('delete', $sourceTag);
            });

            $this->assertCompatibleSources($targetTag, $sources);

            // The merge applies only what the operator reviewed: any change to the
            // tags or their assignments since the preview makes the review stale.
            $preview = (new PreviewTagMergeAction)->handle($targetTag, array_values($sources->all()), $actor);

            if (! hash_equals($preview->fingerprint, $reviewFingerprint)) {
                throw ValidationException::withMessages(['target_tag_id' => __('capell-tags::generic.review_stale')]);
            }

            $merged = 0;

            $targetTag->forceFill(['merged_slug_aliases' => (new BuildMergedTagAliasesAction)->handle($targetTag, $sources)])->save();

            $this->moveTaggables(array_values($sources->modelKeys()), $targetTag->id);

            foreach ($sources as $sourceTag) {
                $sourceTag->delete();
                $merged++;
            }

            return $merged;
        });
    }

    /**
     * @param  EloquentCollection<int, Tag>  $sourceTags
     */
    private function assertCompatibleSources(Tag $targetTag, EloquentCollection $sourceTags): void
    {
        $incompatible = $sourceTags->contains(
            static fn (Tag $sourceTag): bool => $sourceTag->type !== $targetTag->type
                || $sourceTag->site_id !== $targetTag->site_id
                || $sourceTag->workspace_id !== $targetTag->workspace_id,
        );

        if ($incompatible) {
            throw new InvalidArgumentException((string) __('capell-tags::generic.merge_tags_incompatible'));
        }
    }

    /**
     * Re-points every source assignment at the target in three set-based
     * statements, whatever the number of assignments: drop source rows the
     * target already has, keep one row per record among the sources, then move
     * the rest.
     *
     * A duplicate is matched on the taggables unique key (tag, record type and
     * record id). The workspace is not part of that key, so a record tagged in
     * different workspaces is still one assignment per tag: the target's row (or
     * the lowest source tag's row) is the one kept.
     *
     * @param  list<int|string>  $sourceIds
     */
    private function moveTaggables(array $sourceIds, int $targetId): void
    {
        $table = (new Taggable)->getTable();

        // Self-joins rather than correlated subqueries: MySQL refuses a DELETE whose
        // subquery reads the table being deleted from.
        DB::table($table . ' as source')
            ->join($table . ' as target', function (JoinClause $join) use ($targetId): void {
                $join->on('target.taggable_type', '=', 'source.taggable_type')
                    ->on('target.taggable_id', '=', 'source.taggable_id')
                    ->where('target.tag_id', '=', $targetId);
            })
            ->whereIn('source.tag_id', $sourceIds)
            ->delete();

        DB::table($table . ' as source')
            ->join($table . ' as kept', function (JoinClause $join) use ($sourceIds): void {
                $join->on('kept.taggable_type', '=', 'source.taggable_type')
                    ->on('kept.taggable_id', '=', 'source.taggable_id')
                    ->on('kept.tag_id', '<', 'source.tag_id')
                    ->whereIn('kept.tag_id', $sourceIds);
            })
            ->whereIn('source.tag_id', $sourceIds)
            ->delete();

        Taggable::query()->whereIn('tag_id', $sourceIds)->update(['tag_id' => $targetId]);
    }
}
