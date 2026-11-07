<?php

namespace Tests\Fixtures\DataTable;

use Livewire\Component;

/**
 * Minimal Livewire component that renders only the shared toolbar with export enabled and no title slot.
 */
class FixtureToolbarOnly extends Component
{
    public string $search = '';

    public function export(): void {}

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                <x-admin.table.toolbar :table-id="$this->getId()" :filters="[]" :export="true" :search="$search" />
            </div>
        BLADE;
    }
}
