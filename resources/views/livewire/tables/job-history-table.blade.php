{{-- Employee profile: job history on the shared data table layer (reference: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $bulkActions = $this->bulkActions();
    $selectable = $bulkActions !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets('deleteRow');
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
            :search-shortcut="null"
            search-placeholder="Cari jabatan, unit, SK..." />

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
                    @forelse ($rows as $history)
                        @php
                            $key = (string) $history->getAttribute($rowKey);
                            $isSelected = in_array($key, $selected, true);
                        @endphp
                        <tr wire:key="row-{{ $key }}" @class(['transition', 'bg-surface-50 border-l-4 border-l-primary-600' => $isSelected, 'hover:bg-surface-50/75' => ! $isSelected])>
                            @if ($selectable)
                                <x-admin.table.select-cell :key="$key" :label="$history->jabatan" />
                            @endif
                            <td class="px-4 py-3 whitespace-nowrap text-surface-900 font-medium">
                                {{ $history->tmt_awal?->format('d M Y') ?? '-' }} &ndash;
                                <span class="{{ is_null($history->tmt_akhir) ? 'text-primary-600 font-semibold' : 'text-surface-600' }}">
                                    {{ $history->tmt_akhir?->format('d M Y') ?? 'Sekarang' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-surface-900 font-semibold">
                                {{ $history->jabatan }}
                            </td>
                            <td class="px-4 py-3 text-surface-700">
                                {{ $history->unit_kerja }}
                            </td>
                            <td class="px-4 py-3 text-surface-600">
                                {{ $history->level ?? '-' }} / {{ $history->golongan ?? '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($history->jenis_mutasi)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-info-50 text-info-700 border border-info-200">
                                        {{ $history->jenis_mutasi_label }}
                                    </span>
                                @else
                                    <span class="text-surface-500">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-surface-600">
                                @if ($history->nomor_sk || $history->tanggal_sk)
                                    <div class="flex items-center gap-1.5">
                                        <span>{{ $history->nomor_sk ?? '&mdash;' }}</span>
                                        @if ($history->file_sk)
                                            <a href="{{ route('admin.mutasi.downloadSk', $history->id) }}" target="_blank" class="text-danger hover:text-danger-700 transition" title="Unduh Berkas SK PDF (Aman)">
                                                <i class="fas fa-file-pdf text-xs" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                    <div class="text-[11px] text-surface-500">{{ $history->tanggal_sk?->format('d M Y') ?? '' }}</div>
                                @else
                                    <span class="text-surface-500">&mdash;</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @include('admin.employees.partials.job-history-actions', ['row' => $history])
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" message="Belum ada data riwayat jabatan." icon="fas fa-briefcase" />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="riwayat jabatan" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
