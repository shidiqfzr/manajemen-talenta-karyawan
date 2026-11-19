<?php

namespace Tests\Fixtures\DataTable;

use Livewire\Component;

/**
 * Minimal Livewire component rendering only the shared toolbar and table header over a synthetic paginator,
 * so the header copy (count badge, range, export) can be tested with exact numbers.
 */
class FixtureToolbarOnly extends Component
{
    public string $search = '';

    public int $total = 0;

    public ?string $title = null;

    public bool $export = false;

    public function export(): void {}

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-admin.table.toolbar :table-id="$this->getId()" :filters="[]" :search="$search" :has-active-filters="$search !== ''" />
                @php($paginator = new \Illuminate\Pagination\LengthAwarePaginator(array_fill(0, min(25, $total), 'x'), $total, 25, 1))
                <x-admin.table.header :title="$title" :paginator="$paginator" noun="Catatan" :export="$export" />
            </div>
        BLADE;
    }
}
