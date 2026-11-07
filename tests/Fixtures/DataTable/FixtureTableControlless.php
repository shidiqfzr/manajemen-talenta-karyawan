<?php

namespace Tests\Fixtures\DataTable;

use App\Support\DataTable\Filter;

/**
 * FixtureTable variant whose only filter is driven from outside the toolbar (like KPI cards): no field is rendered.
 */
class FixtureTableControlless extends FixtureTable
{
    protected function filters(): array
    {
        return [
            Filter::select('bidang', 'Bidang', ['KEU' => 'KEU', 'TAN' => 'TAN'])->withoutControl(),
        ];
    }
}
