<?php

declare(strict_types=1);

namespace Capell\Tags\Data;

final readonly class TagUsageGroupData
{
    /** @param list<array{label: string, url: ?string}> $records */
    public function __construct(
        public string $type,
        public string $site,
        public int $count,
        public array $records,
    ) {}
}
