<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures\Models;

use Capell\Tags\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;

final class TaggableTestModel extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use HasTags;

    protected $table = 'pages';

    protected $guarded = [];

    #[Override]
    public function getMorphClass(): string
    {
        return 'tags-test-model';
    }
}
