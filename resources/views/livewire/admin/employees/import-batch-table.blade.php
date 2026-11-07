{{-- Riwayat & Persetujuan Impor di atas lapisan tabel data bersama (referensi: tests/Fixtures/DataTable/FixtureTable.php). --}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $selectable = $this->bulkActions() !== [];
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    $columnCount = $this->tableColumnCount();
    $counts = $this->tabCounts;
    $requiresApproval = \App\Services\EmployeeImportGovernanceService::requiresApproval();
@endphp
<div>
    <x-admin.table.card :notice="$tableNotice">
        <nav class="flex flex-wrap items-center gap-1 px-4 pt-3 border-b border-surface-200" aria-label="Filter status impor">
            @foreach ($this::TABS as $key => $label)
                @php $active = ($status === '' ? 'all' : $status) === $key; @endphp
                <button type="button" wire:key="tab-{{ $key }}" data-tab="{{ $key }}"
                    wire:click="$set('status', '{{ $key === 'all' ? '' : $key }}')"
                    @if ($active) aria-current="page" @endif
                    @if ($key === 'pending' && ! $requiresApproval) title="Persetujuan dua pihak belum diaktifkan, sehingga impor langsung tersimpan." @endif
                    class="px-4 py-2.5 text-sm font-semibold border-b-2 -mb-px rounded-t cursor-pointer transition focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $active ? 'border-primary-600 text-primary-800' : 'border-transparent text-surface-600 hover:text-surface-900 hover:border-surface-300' }}">
                    {{ $label }}
                    <span class="ml-1 font-mono tabular-nums text-xs {{ $key === 'pending' && $counts[$key] > 0 ? 'text-warning-800' : 'text-surface-500' }}">{{ $counts[$key] }}</span>
                </button>
            @endforeach
        </nav>
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$this->hasActiveFilters()"
            :search="$search"
            search-placeholder="Cari berkas atau pengaju..." />
        <x-admin.table.header title="Riwayat & Persetujuan" :paginator="$rows" noun="Impor" />

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
                    @forelse ($rows as $log)
                        <tr wire:key="row-{{ $log->id }}" class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 max-w-[280px]">
                                <a href="{{ route('admin.employees.import.batches.show', $log) }}" class="block font-semibold text-surface-900 truncate hover:text-primary-700 hover:underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 rounded" title="{{ $log->file_name }}">{{ $log->file_name }}</a>
                                <span class="block text-xs text-surface-500"><span class="font-mono tabular-nums">{{ number_format($log->total_rows, 0, ',', '.') }}</span> baris &bull; Impor #{{ $log->id }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="block text-surface-900">{{ $log->user_name ?? '-' }}</span>
                                <span class="block text-xs text-surface-500">{{ ($log->submitted_at ?? $log->created_at)->format('d/m/Y H:i') }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-surface-600">
                                    <span><span class="font-mono tabular-nums font-semibold text-surface-900">{{ $log->created_count }}</span> baru</span>
                                    <span><span class="font-mono tabular-nums font-semibold {{ $log->updated_count > 0 ? 'text-surface-900' : 'text-surface-500' }}">{{ $log->updated_count }}</span> diperbarui</span>
                                    <span class="{{ $log->skipped_count > 0 ? 'text-danger-700 font-semibold' : '' }}"><span class="font-mono tabular-nums">{{ $log->skipped_count }}</span> dilewati</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <x-admin.import-status :status="$log->status" :skipped="$log->skipped_count" />
                                @if ($log->checker_name && in_array($log->status, ['completed', 'rejected', 'approved']))
                                    <span class="block mt-1 text-xs text-surface-500">{{ $log->status === 'rejected' ? 'Ditolak' : 'Disetujui' }} {{ $log->checker_name }}</span>
                                @elseif ($log->status === 'rolled_back' && $log->rolled_back_by_name)
                                    <span class="block mt-1 text-xs text-surface-500">Oleh {{ $log->rolled_back_by_name }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <x-admin.row-menu :label="'impor '.$log->file_name">
                                    <x-admin.master.menu-item :href="route('admin.employees.import.batches.show', $log)" :icon="$log->status === 'pending_approval' ? 'fas fa-clipboard-check' : 'fas fa-eye'">
                                        {{ $log->status === 'pending_approval' ? 'Tinjau Pengajuan' : 'Lihat Detail' }}
                                    </x-admin.master.menu-item>
                                </x-admin.row-menu>
                            </td>
                        </tr>
                    @empty
                        @if ($this->hasActiveFilters())
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-filter" message="Tidak ada impor yang cocok dengan filter ini. Ubah filter atau kata kunci pencarian.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-clipboard-list" message="Belum ada riwayat impor. Mulai dari Impor Data Pegawai." />
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="impor" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
