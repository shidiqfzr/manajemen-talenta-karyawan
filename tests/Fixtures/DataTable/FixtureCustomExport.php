<?php

namespace Tests\Fixtures\DataTable;

use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;

/**
 * Test-only workbook a module supplies instead of the default DataTableExport.
 */
class FixtureCustomExport implements FromQuery
{
    public function __construct(private readonly Builder $query) {}

    public function query(): Builder
    {
        return $this->query;
    }
}
