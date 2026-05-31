<?php

declare(strict_types=1);

use Capell\Core\Models\Page;
use Capell\Tags\Models\Concerns\HasTags;

it('keeps the package taggable trait analysed in isolation', function (): void {
    $taggable = new class extends Page
    {
        use HasTags;
    };

    expect(class_uses_recursive($taggable))->toContain(HasTags::class);
});
