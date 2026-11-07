{{-- Riwayat Perubahan Master di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $rowKey = $this->rowKey();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
@endphp
<div>
    {{-- Wilayah live permanen: hasil filter diumumkan walau penomoran halaman tidak tampil. --}}
    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true">{{ number_format($rows->total()) }} catatan ditemukan</div>

    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :export="$this->exportName() !== null"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            search-placeholder="Cari unit, jabatan, admin, atau alasan..." />

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

                <tbody class="divide-y divide-surface-200 text-surface-800 align-top" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($rows as $log)
                        @php $key = (string) $log->getAttribute($rowKey); @endphp
                        <tr wire:key="row-{{ $key }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 text-surface-600 whitespace-nowrap tabular-nums">{{ $log->created_at->translatedFormat('d M Y H:i') }}</td>
                            <td class="px-4 py-3 text-surface-800">{{ $log->user_name }}</td>
                            <td class="px-4 py-3 font-bold text-surface-800">{{ \App\Models\MasterAuditLog::actionLabel($log->action) }}</td>
                            <td class="px-4 py-3 text-surface-600">
                                <span class="block text-[10px] font-bold uppercase text-surface-500">{{ $entityLabels[$log->entity_type] ?? $log->entity_type }}</span>
                                {{ $log->entity_label }}
                            </td>
                            <td class="px-4 py-3 text-surface-600">
                                @forelse ($log->changeSummary() as $line)
                                    <div>{{ $line }}</div>
                                @empty
                                    -
                                @endforelse
                            </td>
                            <td class="px-4 py-3 text-surface-600">{{ $log->reason ?: '-' }}</td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada data yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-clock-rotate-left" message="Belum ada perubahan master yang tercatat." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="catatan" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
