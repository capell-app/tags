<?php

declare(strict_types=1);

namespace Capell\Tags\Data;

use Illuminate\Database\Eloquent\Model;

final readonly class RelatedTaggableData
{
    public function __construct(
        public Model $record,
        public int $sharedTagCount,
    ) {}
}
