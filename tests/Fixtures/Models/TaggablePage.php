<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures\Models;

use Capell\Tags\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;

final class TaggablePage extends Model
{
    use HasTags;

    /** @var string|null */
    protected $table = 'pages';

    /** @var list<string> */
    protected $guarded = [];
}
