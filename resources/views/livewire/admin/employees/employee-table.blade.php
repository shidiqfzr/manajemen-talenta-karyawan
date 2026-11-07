{{--
    Direktori Pegawai di atas lapisan tabel data bersama (referensi: livewire/admin/master/audit-history.blade.php).
    Komponen ini dirender di dalam x-data halaman admin/employees/index: centang baris, menu aksi, modal hapus,
    bilah aksi massal, dan formulir ekspor memakai state Alpine halaman (selectedNiks, pageNiks, confirmDelete, ...),
    sehingga Hapus Massal dan Export tetap mengirim ke rute lamanya.
--}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets();
    // Legacy selection checkbox column (page-managed) + the declared columns.
    $columnCount = $this->tableColumnCount() + 1;
    $hasActiveFilters = $this->hasActiveFilters();
    $total = number_format((int) $rows->total(), 0, ',', '.');
@endphp
<div>
    {{-- Kunci baris halaman ini untuk "Pilih Semua" milik halaman; elemen diganti setiap isi halaman berubah sehingga x-init berjalan lagi,
         dan pilihan yang tidak lagi terlihat dilepas agar Hapus Massal hanya menyentuh baris yang tampil. --}}
    <span hidden wire:key="employee-page-keys-{{ md5(implode(',', $pageKeys)) }}"
        x-init="pageNiks = @js($pageKeys); selectedNiks = selectedNiks.filter(nik => pageNiks.includes(nik)); lastSelectedNik = null"></span>

    <x-admin.table.card :notice="$tableNotice">
        <x-admin.table.toolbar
            :table-id="$this->getId()"
            :filters="$this->filters()"
            :filter-values="$this->activeFilterValues()"
            :has-active-filters="$hasActiveFilters"
            :search="$search"
            search-placeholder="Cari NIK, Nama, Jabatan, atau Unit Kerja..." />

        <x-admin.table.header title="Direktori Pegawai" :paginator="$rows" noun="Pegawai">
            <x-slot:actions>
                <!-- Normal state (no rows selected): export of the current search, filters and sort -->
                <div x-show="selectedNiks.length === 0" class="flex items-center gap-2">
                    <form action="{{ route('admin.employees.export') }}" method="GET" id="tableExportForm" class="inline">
                        @foreach ($this->exportQuery() as $key => $value)
                            <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                        @endforeach

                        <button type="button" @click="triggerExport('tableExportForm')" :disabled="isExporting" id="tableExportBtn"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-surface-900 text-xs font-semibold transition shadow-xs group cursor-pointer disabled:opacity-60 disabled:cursor-wait"
                            title="{{ $hasActiveFilters ? 'Export data pegawai sesuai filter aktif' : 'Export seluruh data pegawai ke Excel' }}">
                            <template x-if="!isExporting">
                                <i class="fas fa-file-excel text-primary-600 text-xs group-hover:scale-110 transition-transform" aria-hidden="true"></i>
                            </template>
                            <template x-if="isExporting">
                                <i class="fas fa-circle-notch fa-spin text-primary-600 text-xs" aria-hidden="true"></i>
                            </template>
                            <span id="tableExportBtnText" x-text="isExporting ? 'Mengekspor...' : 'Export Data'">Export Data</span>
                            <span class="px-1.5 py-0.2 rounded-md bg-surface-100 text-surface-600 font-mono text-[10px]">
                                {{ $total }}
                            </span>
                        </button>
                    </form>
                </div>

                <!-- Selection state (>= 1 rows selected): selection utilities, export selected and bulk delete -->
                <div x-show="selectedNiks.length > 0" x-cloak class="flex flex-wrap items-center gap-2.5 animate-fade-in">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary-100 text-primary-800 font-bold text-xs shadow-2xs">
                        <i class="fas fa-check text-[10px] text-primary-700" aria-hidden="true"></i>
                        <span><strong x-text="selectedNiks.length"></strong> Terpilih</span>
                    </span>
                    <span class="text-xs text-surface-300">•</span>
                    <button type="button" @click="toggleSelectAll(true)"
                        class="text-xs font-semibold text-surface-600 hover:text-primary-700 transition cursor-pointer">
                        Pilih Semua ({{ $rows->count() }})
                    </button>
                    <span class="text-xs text-surface-300">•</span>
                    <button type="button" @click="deselectAll()"
                        class="text-xs font-medium text-surface-500 hover:text-surface-800 transition cursor-pointer">
                        Batalkan Pilihan
                    </button>
                    <span class="text-xs text-surface-300 hidden xl:inline">•</span>
                    <span class="text-[11px] text-surface-500 hidden xl:inline items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded bg-white border border-surface-200 text-surface-500 font-mono text-[10px] shadow-2xs">Shift</kbd> + klik baris untuk rentang
                    </span>

                    <!-- Export Selected -->
                    <form action="{{ route('admin.employees.export') }}" method="GET" id="topExportSelectedForm" class="inline">
                        <template x-for="nik in selectedNiks" :key="'top-export-' + nik">
                            <input type="hidden" name="niks[]" :value="nik">
                        </template>
                        <button type="button" @click="triggerExport('topExportSelectedForm')" :disabled="isExporting"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-primary-800 text-xs font-semibold transition shadow-2xs cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                            <template x-if="!isExporting">
                                <i class="fas fa-file-excel text-primary-600 text-xs" aria-hidden="true"></i>
                            </template>
                            <template x-if="isExporting">
                                <i class="fas fa-circle-notch fa-spin text-primary-600 text-xs" aria-hidden="true"></i>
                            </template>
                            <span>Export (<span x-text="selectedNiks.length"></span>)</span>
                        </button>
                    </form>

                    <!-- Bulk Delete Trigger (Clean Subdued Outline) -->
                    <button type="button" @click="bulkDeleteModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-danger-200 bg-white text-danger hover:bg-danger-50 text-xs font-semibold transition shadow-2xs cursor-pointer">
                        <i class="fas fa-trash-alt text-xs" aria-hidden="true"></i>
                        <span>Hapus</span>
                    </button>
                </div>
            </x-slot:actions>
        </x-admin.table.header>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                <x-admin.table.head
                    :columns="$columns"
                    :sort-field="$sortField"
                    :sort-direction="$sortDirection"
                    :selectable="false">
                    <x-slot:leading>
                        <!-- Dedicated Clean Checkbox Column -->
                        <th scope="col" class="py-3.5 pl-5 pr-2 w-10 text-center">
                            <input type="checkbox"
                                   class="block mx-auto w-4 h-4 cursor-pointer accent-primary-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-500"
                                   aria-label="Pilih semua pegawai di halaman ini"
                                   title="Pilih semua pegawai di halaman ini (Shift+klik atau Shift+Spasi pada baris untuk rentang)"
                                   :checked="isAllSelected"
                                   x-effect="$el.indeterminate = !isAllSelected && selectedNiks.length > 0"
                                   @click="toggleSelectAll(!isAllSelected); $nextTick(() => $el.checked = isAllSelected)">
                        </th>
                    </x-slot:leading>
                </x-admin.table.head>

                <tbody class="divide-y divide-surface-200 text-sm" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($rows as $employee)
                        <x-admin.employee-table-row :employee="$employee" :term="$search" wire:key="row-{{ $employee->nik }}" />
                    @empty
                        @if ($hasActiveFilters)
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-users-slash" message="Tidak ada pegawai yang sesuai dengan pencarian atau filter.">
                                <x-admin.button variant="secondary" size="sm" icon="fas fa-rotate-left" wire:click="resetFilters" target="resetFilters">Reset Filter</x-admin.button>
                            </x-admin.table.empty-state>
                        @else
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-users-slash" message="Belum ada data karyawan terdaftar di sistem. Anda dapat menambah karyawan baru atau mengimpor dari file Excel.">
                                <a href="{{ route('admin.employees.create') }}"
                                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition">
                                    <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                                    <span>Tambah Karyawan Pertama</span>
                                </a>
                            </x-admin.table.empty-state>
                        @endif
                    @endforelse
                </tbody>

                <x-admin.table.skeleton :columns="$columnCount" :target="$loadingTargets" />
            </table>
        </div>

        <x-admin.table.pagination :paginator="$rows" noun="pegawai" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
