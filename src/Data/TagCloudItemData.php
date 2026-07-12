<?php

declare(strict_types=1);

namespace Capell\Tags\Data;

use Capell\Tags\Models\Tag;

final readonly class TagCloudItemData
{
    public function __construct(
        public Tag $tag,
        public int $usageCount,
        public int $weight,
    ) {}
}
