<?php

namespace Tests\Fixtures\DataTable;

use Livewire\Component;

/**
 * Minimal Livewire component that renders only the shared toolbar with export enabled and no title slot.
 * The result and total counts can be given as mount parameters to test the summary bar.
 */
class FixtureToolbarOnly extends Component
{
    public string $search = '';

    public ?int $resultCount = null;

    public ?int $totalCount = null;

    public function export(): void {}

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-admin.table.toolbar :table-id="$this->getId()" :filters="[]" :export="true" :search="$search" :has-active-filters="$search !== ''" :result-count="$resultCount" :total-count="$totalCount" noun="catatan" />
            </div>
        BLADE;
    }
}
