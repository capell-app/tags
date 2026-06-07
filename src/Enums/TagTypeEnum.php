<?php

declare(strict_types=1);

namespace Capell\Tags\Enums;

use Filament\Support\Contracts\HasLabel;

enum TagTypeEnum: string implements HasLabel
{
    case Article = 'article';
    case Content = 'content';
    case Page = 'page';

    public function getLabel(): string
    {
        return __('capell-tags::form.type_' . $this->value);
    }
}
