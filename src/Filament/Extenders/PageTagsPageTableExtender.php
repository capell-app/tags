<?php

declare(strict_types=1);

namespace Capell\Tags\Filament\Extenders;

use Capell\Admin\Contracts\Extenders\PageTableExtender;
use Capell\Tags\Filament\Actions\ManagePageTagsBulkAction;
use Filament\Actions\BulkAction;
use Filament\Tables\Columns\Column;
use Filament\Tables\Filters\BaseFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class PageTagsPageTableExtender implements PageTableExtender
{
    /**
     * @return array<int, Column>
     */
    public function getColumns(): array
    {
        return [];
    }

    /**
     * @return array<int, BulkAction>
     */
    public function getBulkActions(): array
    {
        return [
            ManagePageTagsBulkAction::make(),
        ];
    }

    /**
     * @return array<int, BaseFilter>
     */
    public function getFilters(): array
    {
        return [];
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    public function modifyQuery(Builder $query): Builder
    {
        return $query;
    }
}
