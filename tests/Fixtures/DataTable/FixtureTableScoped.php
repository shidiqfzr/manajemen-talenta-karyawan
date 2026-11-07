<?php

namespace Tests\Fixtures\DataTable;

use Illuminate\Database\Eloquent\Builder;

/**
 * FixtureTable variant whose base query is already scoped (only the KEU rows).
 */
class FixtureTableScoped extends FixtureTable
{
    protected function baseQuery(): Builder
    {
        return FixtureRow::query()->where('bidang', 'KEU');
    }
}
