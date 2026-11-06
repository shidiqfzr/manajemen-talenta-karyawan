<?php

namespace Tests\Fixtures\DataTable;

use App\Livewire\Concerns\WithDataTable;
use App\Support\DataTable\BulkAction;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use RuntimeException;

/**
 * Minimal module component exercising the WithDataTable trait in tests.
 */
class FixtureTable extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $bidang = '';

    protected function baseQuery(): Builder
    {
        return FixtureRow::query();
    }

    protected function columns(): array
    {
        return [
            Column::make('nama', 'Nama')->sortable()->searchable()->exportable(),
            Column::make('kode', 'Kode')->exportable(),
            Column::make('nilai', 'Nilai')->sortable()->mono()->exportable(),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('bidang', 'Bidang', ['KEU' => 'KEU', 'TAN' => 'TAN']),
        ];
    }

    protected function bulkActions(): array
    {
        return [
            BulkAction::make('nonaktifkan', 'Nonaktifkan')->ability('manage-fixture'),
            BulkAction::make('gagal', 'Gagal'),
            BulkAction::make('diam', 'Diam'),
        ];
    }

    protected function bulkDiam(Builder $targets): void
    {
        // Intentionally returns nothing.
    }

    protected function exportName(): ?string
    {
        return 'tabel-uji';
    }

    protected function bulkNonaktifkan(Builder $targets): int
    {
        return $targets->update(['aktif' => false]);
    }

    protected function bulkGagal(Builder $targets): int
    {
        $targets->update(['aktif' => false]);

        throw new RuntimeException('Simulated handler failure.');
    }

    protected function defaultSort(): array
    {
        return ['nama', 'asc'];
    }

    protected function tableLabel(): string
    {
        return 'Tabel Uji';
    }

    /**
     * Reference module view for the shared table components (copied by real tables).
     *
     * Protected module-contract methods (`$this->filters()`, `$this->columns()`, ...) are callable here:
     * Livewire evaluates the view bound to the component instance.
     */
    public function render(): string
    {
        return <<<'BLADE'
            @php
                $rows = $this->rows;
                $columns = $this->columns();
                $bulkActions = $this->bulkActions();
                $selectable = $bulkActions !== [];
                $pageKeys = $this->tablePageKeys();
                $loadingTargets = $this->tableLoadingTargets();
                $columnCount = $this->tableColumnCount();
            @endphp
            <div>
                <x-admin.table.card :notice="$tableNotice">
                    <x-admin.table.toolbar
                        :table-id="$this->getId()"
                        :filters="$this->filters()"
                        :filter-values="$this->activeFilterValues()"
                        :export="$this->exportName() !== null"
                        :has-active-filters="$this->hasActiveFilters()"
                        :search="$search"
                        search-placeholder="Cari nama...">
                        <x-slot:title><h2 class="text-sm font-bold text-surface-900">Tabel Uji</h2></x-slot:title>
                    </x-admin.table.toolbar>

                    @if ($selectable)
                        <x-admin.table.bulk-bar
                            :actions="$bulkActions"
                            :selected="$selected"
                            :select-all-matching="$selectAllMatching"
                            :selected-count="$this->selectedCount()"
                            :matching-count="$rows->total()"
                            :page-keys="$pageKeys" />
                    @endif

                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-xs">
                            <x-admin.table.head
                                :columns="$columns"
                                :sort-field="$sortField"
                                :sort-direction="$sortDirection"
                                :selectable="$selectable"
                                :page-keys="$pageKeys"
                                :selected="$selected"
                                :select-all-matching="$selectAllMatching" />

                            <tbody class="divide-y divide-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                                @forelse ($rows as $row)
                                    @php
                                        $key = (string) $row->getKey();
                                        $isSelected = in_array($key, $selected, true);
                                    @endphp
                                    <tr wire:key="row-{{ $key }}" @class(['transition', 'bg-surface-50 border-l-4 border-l-primary-600' => $isSelected, 'hover:bg-surface-50/75' => ! $isSelected])>
                                        @if ($selectable)
                                            <x-admin.table.select-cell :key="$key" :label="$row->nama" />
                                        @endif
                                        <td class="px-4 py-3 font-semibold text-surface-900">{{ $row->nama }}</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ $row->kode }}</td>
                                        <td class="px-4 py-3 font-mono tabular-nums">{{ $row->nilai }}</td>
                                    </tr>
                                @empty
                                    @if ($this->hasActiveFilters())
                                        <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
                                            <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset filter</x-admin.button>
                                        </x-admin.table.empty-state>
                                    @else
                                        <x-admin.table.empty-state :colspan="$columnCount" message="Belum ada data." />
                                    @endif
                                @endforelse
                            </tbody>

                            <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
                        </table>
                    </div>

                    <x-admin.table.pagination :paginator="$rows" noun="data" :per-page-options="$this->perPageOptions()" />
                </x-admin.table.card>
            </div>
            BLADE;
    }
}
