<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures;

final class DenyTagCreationPolicy
{
    public function create(mixed $user): bool
    {
        return false;
    }
}
