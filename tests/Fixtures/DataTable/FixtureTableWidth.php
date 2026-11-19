<?php

namespace Tests\Fixtures\DataTable;

use App\Support\DataTable\Column;

/**
 * FixtureTable variant whose first column carries a header width class.
 */
class FixtureTableWidth extends FixtureTable
{
    protected function columns(): array
    {
        return [
            Column::make('nama', 'Nama')->sortable()->searchable()->exportable()->width('w-[30%]'),
            Column::make('kode', 'Kode')->exportable(),
            Column::make('nilai', 'Nilai')->sortable()->mono()->exportable(),
        ];
    }
}
