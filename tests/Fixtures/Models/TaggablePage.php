<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures\Models;

use Capell\Core\Models\Page;
use Capell\Tags\Models\Concerns\HasTags;

final class TaggablePage extends Page
{
    use HasTags;

    /** @var string|null */
    protected $table = 'pages';
}
