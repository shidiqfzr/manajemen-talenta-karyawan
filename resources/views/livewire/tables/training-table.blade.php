{{-- Employee profile: training history on the shared data table layer (reference: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $bulkActions = $this->bulkActions();
    $selectable = $bulkActions !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets('detachTraining');
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
            :search-shortcut="null"
            search-placeholder="Cari judul, penyelenggara..." />

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
                    @forelse ($rows as $training)
                        @php
                            $key = (string) $training->getAttribute($rowKey);
                            $isSelected = in_array($key, $selected, true);
                            $empPivot = $training->employees->first();
                            $sertifikat = $empPivot?->pivot?->sertifikat;
                        @endphp
                        <tr wire:key="row-{{ $key }}" @class(['transition', 'bg-surface-50 border-l-4 border-l-primary-600' => $isSelected, 'hover:bg-surface-50/75' => ! $isSelected])>
                            @if ($selectable)
                                <x-admin.table.select-cell :key="$key" :label="$training->judul" />
                            @endif
                            <td class="px-4 py-3 text-surface-900 font-semibold">
                                {{ $training->judul }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-surface-700">
                                {{ $training->tanggal_mulai?->format('d M Y') ?? '-' }}
                                @if ($training->tanggal_akhir)
                                    &ndash; {{ $training->tanggal_akhir->format('d M Y') }}
                                @endif
                            </td>
                            <td class="px-4 py-3 text-surface-700">
                                {{ $training->penyelenggara ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-surface-600">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-700">
                                    {{ $training->jenis ?? '-' }}
                                </span>
                                @if ($training->metode)
                                    <span class="text-xs text-surface-500 ml-1">({{ $training->metode }})</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @if ($sertifikat)
                                    <a href="{{ asset('storage/' . $sertifikat) }}" target="_blank"
                                       class="inline-flex items-center px-2.5 py-1 bg-primary-100 text-primary-700 text-xs font-medium rounded-full hover:bg-primary-200 transition">
                                        <i class="fas fa-download mr-1" aria-hidden="true"></i>Unduh
                                    </a>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 bg-warning-50 text-warning-700 text-xs font-medium rounded-full border border-warning-200">
                                        <i class="fas fa-minus-circle mr-1" aria-hidden="true"></i>Tidak Ada
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-center whitespace-nowrap">
                                @include('admin.employees.partials.training-actions', ['row' => $training])
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" message="Belum ada riwayat pelatihan." icon="fas fa-chalkboard-teacher" />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="pelatihan" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
