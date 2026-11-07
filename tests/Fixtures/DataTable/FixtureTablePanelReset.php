<?php

namespace Tests\Fixtures\DataTable;

use App\Support\DataTable\Filter;

/**
 * FixtureTable variant with one filter that has a field in the panel (bidang) and one driven from outside (aktif).
 */
class FixtureTablePanelReset extends FixtureTable
{
    public string $aktif = '';

    protected function filters(): array
    {
        return [
            Filter::select('bidang', 'Bidang', ['KEU' => 'KEU', 'TAN' => 'TAN']),
            Filter::boolean('aktif', 'Aktif')
                ->withoutControl()
                ->apply(fn ($query) => $query->where('aktif', true)),
        ];
    }
}
