<?php

namespace Tests\Fixtures\DataTable;

use Livewire\Component;

/**
 * Minimal Livewire component rendering the shared toolbar bound to a custom search property and reset action.
 */
class FixtureToolbarCustomSearch extends Component
{
    public string $seatQuery = '';

    public function resetSeatFilters(): void
    {
        $this->seatQuery = '';
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-admin.table.toolbar search-model="seatQuery" reset-action="resetSeatFilters" :filters="[]" :search="$seatQuery" :has-active-filters="$seatQuery !== ''" />
            </div>
        BLADE;
    }
}
