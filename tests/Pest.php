<?php

declare(strict_types=1);

use Capell\Tags\Tests\TagsTestCase;

pest()->extend(TagsTestCase::class)->group('tags')->in(__DIR__);
