<?php

namespace Tests\Fixtures\DataTable;

use Illuminate\Database\Eloquent\Builder;

/**
 * FixtureTable variant whose summary total follows its own scope (only the TAN rows) instead of the base query.
 */
class FixtureTableCustomTotal extends FixtureTable
{
    protected function unfilteredQuery(): Builder
    {
        return FixtureRow::query()->where('bidang', 'TAN');
    }
}
