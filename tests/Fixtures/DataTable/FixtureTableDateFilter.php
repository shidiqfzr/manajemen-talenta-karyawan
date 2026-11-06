<?php

namespace Tests\Fixtures\DataTable;

use App\Support\DataTable\Filter;
use Livewire\Attributes\Url;

/**
 * FixtureTable variant with a day range on the `tanggal` column.
 */
class FixtureTableDateFilter extends FixtureTable
{
    #[Url(except: '')]
    public string $dari = '';

    #[Url(except: '')]
    public string $sampai = '';

    protected function filters(): array
    {
        return [
            ...parent::filters(),
            Filter::dateFrom('dari', 'Dari Tanggal', 'tanggal'),
            Filter::dateTo('sampai', 'Sampai Tanggal', 'tanggal'),
        ];
    }
}
