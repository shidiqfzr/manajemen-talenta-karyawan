<?php

namespace Tests\Fixtures\DataTable;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * FixtureTable variant that supplies its own export file name and workbook.
 */
class FixtureTableCustomExport extends FixtureTable
{
    protected function exportFileName(): string
    {
        return 'berkas-khusus.xlsx';
    }

    protected function buildExport(Builder $query, ?User $user): object
    {
        return new FixtureCustomExport($query);
    }
}
