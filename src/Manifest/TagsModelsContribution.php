<?php

declare(strict_types=1);

namespace Capell\Tags\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class TagsModelsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }
}
