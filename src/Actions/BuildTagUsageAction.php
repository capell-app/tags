<?php

declare(strict_types=1);

namespace Capell\Tags\Actions;

use Capell\Admin\Support\SiteScope;
use Capell\Core\Models\Site;
use Capell\Tags\Data\TagUsageGroupData;
use Capell\Tags\Models\Tag;
use Capell\Tags\Models\Taggable;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;

final class BuildTagUsageAction
{
    /**
     * @param  list<Tag>  $tags
     * @return list<TagUsageGroupData>
     */
    public function handle(array $tags, Authenticatable $actor): array
    {
        $gate = Gate::forUser($actor);
        foreach ($tags as $tag) {
            $gate->authorize('view', $tag);
        }

        $global = SiteScope::isGlobalActor($actor);
        $sites = Site::query()->get()->keyBy('id');
        $assignedSites = $global ? [] : $actor->getAssignedSiteIds()->all();
        $groups = [];
        $pivots = Taggable::query()->whereIn('tag_id', array_map(fn (Tag $tag): int => $tag->id, $tags))->get();
        foreach ($pivots->groupBy('taggable_type') as $type => $rows) {
            $class = Relation::getMorphedModel((string) $type) ?? $type;
            $models = collect();
            if (is_string($class) && is_subclass_of($class, Model::class)) {
                $model = new $class;
                if ($model->getConnection()->getSchemaBuilder()->hasTable($model->getTable())) {
                    $models = $model->newQuery()->whereKey($rows->pluck('taggable_id')->unique())->get()->keyBy(fn (Model $record): string => $this->label($record->getKey()));
                }
            }

            foreach ($rows->unique(fn (Taggable $pivot): string => $pivot->taggable_id . ':' . $pivot->workspace_id) as $pivot) {
                $record = $models->get((string) $pivot->taggable_id);
                $siteValue = $record instanceof Model && $record->hasAttribute('site_id') ? $record->getAttribute('site_id') : null;
                $siteId = is_numeric($siteValue) ? (int) $siteValue : null;
                if ($record instanceof Model && ((! $global && $siteId !== null && ! in_array($siteId, $assignedSites)) || $gate->denies('view', $record))) {
                    continue;
                }

                $resource = $record instanceof Model ? Filament::getCurrentPanel()?->getModelResource($record) : null;
                $typeLabel = $resource !== null ? $resource::getPluralModelLabel() : ($record instanceof Model ? class_basename($record) : __('capell-tags::generic.usage_unavailable'));
                $siteLabel = $siteId !== null ? $this->label($sites->get($siteId)?->getAttribute('name')) : (string) __('capell-tags::generic.usage_no_site');
                $key = $typeLabel . ':' . ($siteId ?? 'none');
                $previous = $groups[$key] ?? new TagUsageGroupData((string) $typeLabel, $siteLabel, 0, []);
                $records = $previous->records;
                if ($record instanceof Model) {
                    $label = $resource !== null ? $resource::getRecordTitle($record) : ($record->hasAttribute('name') ? $record->getAttribute('name') : ($record->hasAttribute('title') ? $record->getAttribute('title') : null));
                    $url = null;
                    if ($resource !== null && $resource::hasPage('view')) {
                        $url = $resource::getUrl('view', ['record' => $record]);
                    } elseif ($resource !== null && $resource::hasPage('edit') && $gate->allows('update', $record)) {
                        $url = $resource::getUrl('edit', ['record' => $record]);
                    }

                    $records[] = ['label' => is_scalar($label) ? (string) $label : (string) __('capell-tags::generic.usage_record'), 'url' => $url];
                }

                $groups[$key] = new TagUsageGroupData($previous->type, $previous->site, $previous->count + 1, $records);
            }
        }

        return array_values($groups);
    }

    private function label(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
