<?php

namespace Tests\Fixtures\DataTable;

use App\Support\DataTable\BulkAction;
use App\Support\DataTable\Filter;
use Illuminate\Database\Eloquent\Builder;

/**
 * FixtureTable variant with a boolean (chip) filter and a destructive bulk action.
 */
class FixtureTableBooleanFilter extends FixtureTable
{
    public string $aktif = '';

    protected function filters(): array
    {
        return [
            ...parent::filters(),
            Filter::boolean('aktif', 'Hanya Aktif')->icon('fas fa-user-check'),
        ];
    }

    protected function bulkActions(): array
    {
        return [
            ...parent::bulkActions(),
            BulkAction::make('hapus', 'Hapus')->icon('fas fa-trash')->danger('Hapus :count data terpilih secara permanen?'),
        ];
    }

    protected function bulkHapus(Builder $targets): int
    {
        return $targets->delete();
    }
}
