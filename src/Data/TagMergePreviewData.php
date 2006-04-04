<?php

declare(strict_types=1);

namespace Capell\Tags\Data;

final readonly class TagMergePreviewData
{
    /**
     * @param  list<string>  $sources
     * @param  list<TagUsageGroupData>  $usage
     * @param  array<string, list<string>>  $aliases
     */
    public function __construct(
        public string $target,
        public array $sources,
        public array $usage,
        public int $affected,
        public int $duplicates,
        public array $aliases,
        public string $fingerprint,
    ) {}
}
