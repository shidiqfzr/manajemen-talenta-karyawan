{{-- Employee profile: evaluation history on the shared data table layer (reference: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $bulkActions = $this->bulkActions();
    $selectable = $bulkActions !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $rows->getCollection()->map(fn ($row) => (string) $row->getAttribute($rowKey))->all();
    $loadingTargets = collect(['search', 'sortBy', 'perPage', 'gotoPage', 'nextPage', 'previousPage', 'resetFilters', $this->filterProperties()])->filter()->implode(', ');
    $columnCount = count(array_filter($columns, fn ($column) => ! $column->isExportOnly())) + ($selectable ? 1 : 0);
@endphp
<div>
    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :export="$this->exportName() !== null"
            :has-active-filters="$this->hasActiveFilters()"
            :search-shortcut="null"
            search-placeholder="Cari bidang tugas, lembaga, 9-box..." />

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
            <table class="w-full text-left border-collapse text-xs" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                <x-admin.table.head
                    :columns="$columns"
                    :sort-field="$sortField"
                    :sort-direction="$sortDirection"
                    :selectable="$selectable"
                    :page-keys="$pageKeys"
                    :selected="$selected"
                    :select-all-matching="$selectAllMatching" />

                <tbody class="divide-y divide-surface-100 text-surface-800" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($rows as $evaluation)
                        @php
                            $key = (string) $evaluation->getAttribute($rowKey);
                            $isSelected = in_array($key, $selected, true);
                        @endphp
                        <tr wire:key="row-{{ $key }}" @class(['transition', 'bg-surface-50 border-l-4 border-l-primary-600' => $isSelected, 'hover:bg-surface-50/75' => ! $isSelected])>
                            @if ($selectable)
                                <x-admin.table.select-cell :key="$key" :label="$evaluation->bidang_tugas ?? $key" />
                            @endif
                            <td class="px-4 py-3 whitespace-nowrap text-surface-900 font-medium">
                                {{ $evaluation->tanggal_pelaksanaan_asesmen ? \Carbon\Carbon::parse($evaluation->tanggal_pelaksanaan_asesmen)->format('d M Y') : '-' }}
                                @if ($evaluation->expired_asesmen)
                                    <div class="text-[11px] text-surface-500">s/d {{ \Carbon\Carbon::parse($evaluation->expired_asesmen)->format('d M Y') }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-lg text-xs font-bold bg-primary-50 text-primary-700 border border-primary-200">
                                    {{ is_null($evaluation->nilai_tertimbang) ? '-' : number_format($evaluation->nilai_tertimbang, 2) }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="space-y-0.5">
                                    <div class="text-surface-700">
                                        SMKBK: <span class="font-medium text-surface-900">{{ number_format($evaluation->skor_smkbk_9box ?? 0, 1) }}</span> /
                                        CLI: <span class="font-medium text-surface-900">{{ number_format($evaluation->skor_cli_9box ?? 0, 1) }}</span>
                                    </div>
                                    @if ($evaluation->kategori_9box)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-info text-white">
                                            {{ $evaluation->kategori_9box }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-4 py-3 text-surface-700">
                                {{ $evaluation->bidang_tugas ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-surface-600">
                                <div>{{ $evaluation->lembaga_asesmen ?? '-' }}</div>
                                <div class="text-[11px] text-surface-500">{{ $evaluation->kategori_asesmen ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @include('admin.employees.partials.evaluation-actions', ['row' => $evaluation])
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" message="Belum ada riwayat penilaian." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="evaluasi" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
