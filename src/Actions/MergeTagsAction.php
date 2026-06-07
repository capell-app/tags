<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsObject;

final class MergeTagsAction
{
    use AsObject;

    /**
     * @param  iterable<int, Tag>  $sourceTags
     */
    public function handle(Tag $targetTag, iterable $sourceTags): int
    {
        $sources = EloquentCollection::make($sourceTags)
            ->filter(static fn (Tag $sourceTag): bool => (int) $sourceTag->getKey() !== (int) $targetTag->getKey())
            ->values();

        if ($sources->isEmpty()) {
            return 0;
        }

        $this->assertCompatibleSources($targetTag, $sources);

        return DB::transaction(function () use ($targetTag, $sources): int {
            $merged = 0;

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
}
