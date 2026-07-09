<?php

declare(strict_types=1);

use Capell\Admin\Contracts\Extenders\PageTableExtender;
use Capell\Core\Models\Page;
use Capell\Tags\Filament\Actions\ManagePageTagsBulkAction;
use Capell\Tags\Filament\Extenders\PageTagsPageTableExtender;
use Capell\Tags\Models\Tag;
use Filament\Actions\BulkAction;

it('registers page tags through the page table extender contract', function (): void {
    $extenders = collect(app()->tagged(PageTableExtender::TAG))
        ->map(fn (PageTableExtender $extender): string => $extender::class);

    expect($extenders)->toContain(PageTagsPageTableExtender::class);
});

it('contributes the manage page tags bulk action', function (): void {
    $actionNames = collect((new PageTagsPageTableExtender)->getBulkActions())
        ->map(fn (BulkAction $action): string => $action->getName())
        ->all();

    expect($actionNames)->toBe(['managePageTags'])
        ->and(ManagePageTagsBulkAction::make()->getName())->toBe('managePageTags');
});

it('registers pages as taggable models when the tags package is installed', function (): void {
    $page = Page::factory()->create();

    $page->load('tags');

    expect($page->getRelation('tags'))->toBeEmpty()
        ->and((new Tag)->pages()->getRelated())->toBeInstanceOf(Page::class);
});
