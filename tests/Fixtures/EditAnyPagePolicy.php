<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures;

use Capell\Core\Models\Page;

final class EditAnyPagePolicy
{
    public function update(mixed $user, Page $page): bool
    {
        return true;
    }
}
