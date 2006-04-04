<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Tags\Data\TagMergePreviewData;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class PreviewTagMergeAction
{
    /** @param list<Tag> $sources */
    public function handle(Tag $target, array $sources, Authenticatable $actor): TagMergePreviewData
    {
        $target = Tag::query()->findOrFail($target->id);
        $sources = array_map(fn (Tag $tag): Tag => Tag::query()->findOrFail($tag->id), $sources);
        $gate = Gate::forUser($actor);
        $gate->authorize('update', $target);
        if ($sources === [] || collect($sources)->contains(fn (Tag $source): bool => $source->is($target))) {
            throw ValidationException::withMessages(['target_tag_id' => __('capell-tags::generic.merge_sources_required')]);
        }

        foreach ($sources as $source) {
            $gate->authorize('delete', $source);
            if ($source->type !== $target->type || $source->site_id !== $target->site_id || $source->workspace_id !== $target->workspace_id) {
                throw ValidationException::withMessages(['target_tag_id' => __('capell-tags::generic.merge_tags_incompatible')]);
            }
        }

        $tags = new Collection([$target, ...$sources])->sortBy('id')->values();
        $pivots = Taggable::query()->whereIn('tag_id', $tags->modelKeys())->orderBy('tag_id')->orderBy('taggable_type')->orderBy('taggable_id')->orderBy('workspace_id')->get();
        $key = fn (Taggable $pivot): string => $pivot->taggable_type . ':' . $pivot->taggable_id . ':' . $pivot->workspace_id;
        $sourcePivots = $pivots->where('tag_id', '!=', $target->id);
        $affected = $sourcePivots->unique($key)->count();
        $fingerprint = hash('sha256', serialize([$actor->getAuthIdentifier(), $target->id, $tags->map(fn (Tag $tag): array => $tag->getRawOriginal())->all(), $pivots->map(fn (Taggable $pivot): array => $pivot->getRawOriginal())->all()]));

        return new TagMergePreviewData(
            $this->name($target),
            array_map($this->name(...), $sources),
            (new BuildTagUsageAction)->handle($sources, $actor),
            $affected,
            $pivots->count() - $pivots->unique($key)->count(),
            (new BuildMergedTagAliasesAction)->handle($target, new Collection($sources)),
            $fingerprint,
        );
    }

    private function name(Tag $tag): string
    {
        $name = $tag->getAttribute('name');

        return is_string($name) ? $name : '';
    }
}
