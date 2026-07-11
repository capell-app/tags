<?php

declare(strict_types=1);

namespace Capell\Tags\Data;

use Capell\Tags\Models\Tag;

final readonly class ResolvedTagSlugData
{
    public function __construct(
        public Tag $tag,
        public string $requestedSlug,
        public string $canonicalSlug,
    ) {}

    public function shouldRedirect(): bool
    {
        return $this->requestedSlug !== $this->canonicalSlug;
    }
}
