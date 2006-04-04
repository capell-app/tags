<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static int run(Tag $targetTag, iterable<int, Tag> $sourceTags, ?Authenticatable $actor = null, ?string $reviewFingerprint = null)
 */
final class MergeTagsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<int, Tag>  $sourceTags
     */
    public function handle(Tag $targetTag, iterable $sourceTags, ?Authenticatable $actor = null, ?string $reviewFingerprint = null): int
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
            if ($reviewFingerprint !== null) {
                $preview = (new PreviewTagMergeAction)->handle($targetTag, array_values($sources->all()), $this->actor($actor));
                if (! hash_equals($preview->fingerprint, $reviewFingerprint)) {
                    throw ValidationException::withMessages(['target_tag_id' => __('capell-tags::generic.review_stale')]);
                }
            }

            $gate = Gate::forUser($this->actor($actor));
            $gate->authorize('update', $targetTag);

            $sources->each(static function (Tag $sourceTag) use ($gate): void {
                $gate->authorize('delete', $sourceTag);
            });

            $this->assertCompatibleSources($targetTag, $sources);

            $merged = 0;

            $targetTag->forceFill(['merged_slug_aliases' => (new BuildMergedTagAliasesAction)->handle($targetTag, $sources)])->save();

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
                    $this->pivotQuery($taggable)->delete();

                    return;
                }

                $this->pivotQuery($taggable)->update(['tag_id' => $targetTag->getKey()]);
            });
    }

    /** @return Builder<Taggable> */
    private function pivotQuery(Taggable $pivot): Builder
    {
        return Taggable::query()->where('tag_id', $pivot->tag_id)
            ->where('taggable_type', $pivot->taggable_type)
            ->where('taggable_id', $pivot->taggable_id)
            ->where('workspace_id', $pivot->workspace_id);
    }

    private function actor(?Authenticatable $actor): Authenticatable
    {
        $actor ??= auth()->user();

        if (! $actor instanceof Authenticatable) {
            throw new AuthorizationException;
        }

        return $actor;
    }
}
