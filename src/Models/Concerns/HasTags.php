<?php

declare(strict_types=1);

namespace Capell\Tags\Models\Concerns;

use ArrayAccess;
use Capell\Core\Models\Language;
use Capell\Core\Models\Page;
use Capell\Core\Models\Site;
use Capell\Tags\Models\Tag;

/**
 * @mixin Page
 */
trait HasTags
{
    use \Spatie\Tags\HasTags;

    /**
     * @param  array<int|string, string|Tag>|ArrayAccess<int|string, string|Tag>  $tags
     */
    public function syncTagsWithType(array|ArrayAccess $tags, ?string $type = null): static
    {
        /** @var class-string<Tag> $className */
        $className = static::getTagClassName();
        $siteId = $this->tagSiteId();

        if ($this->languages->isNotEmpty()) {
            $tagRecords = collect();

            $this->languages->each(function (Language $language) use (&$tagRecords, &$tags, $className, $type, $siteId): void {
                $tagRecords->push($className::findOrCreateForSite($tags, $type, $language->code, $siteId));
            });

            $tags = $tagRecords->flatten();
        } else {
            $tags = collect($className::findOrCreateForSite($tags, $type, siteId: $siteId));
        }

        $this->syncTagIds($tags->pluck('id')->toArray(), $type);

        return $this;
    }

    private function tagSiteId(): ?int
    {
        $siteId = $this->getAttribute('site_id');

        if (is_numeric($siteId)) {
            return (int) $siteId;
        }

        if ($this->relationLoaded('site')) {
            $site = $this->getRelation('site');

            if (! $site instanceof Site) {
                return null;
            }

            $siteKey = $site->getKey();

            return is_int($siteKey) ? $siteKey : null;
        }

        return null;
    }
}
