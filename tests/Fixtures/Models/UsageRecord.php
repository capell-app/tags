<?php

declare(strict_types=1);

namespace Capell\Tags\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class UsageRecord extends Model
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    use SoftDeletes;

    public $timestamps = false;

    protected $table = 'tag_usage_records';

    protected $guarded = [];
}
