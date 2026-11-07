{{--
    Direktori Pegawai di atas lapisan tabel data bersama (referensi: livewire/admin/master/audit-history.blade.php).
    Kartu KPI dan strip tata kelola dirender di sini agar sorotan dan tautannya selalu mengikuti state tabel saat ini;
    klik kartu memanggil $set / clearKpiFilters (nilai divalidasi seperti filter lain), href tetap ada sebagai cadangan tanpa JS.
    Komponen ini dirender di dalam x-data halaman admin/employees/index: centang baris, menu aksi, modal hapus,
    bilah aksi massal, dan formulir ekspor memakai state Alpine halaman (selectedNiks, pageNiks, confirmDelete, ...),
    sehingga Hapus Massal dan Export tetap mengirim ke rute lamanya.
--}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $pageKeys = $this->tablePageKeys();
    $loadingTargets = $this->tableLoadingTargets('clearKpiFilters');
    // Legacy selection checkbox column (page-managed) + the declared columns.
    $columnCount = $this->tableColumnCount() + 1;
    $hasActiveFilters = $this->hasActiveFilters();
    $total = number_format((int) $rows->total(), 0, ',', '.');
@endphp
<div class="space-y-6">
    {{-- Kunci baris halaman ini untuk "Pilih Semua" milik halaman; elemen diganti setiap isi halaman berubah sehingga x-init berjalan lagi,
         dan pilihan yang tidak lagi terlihat dilepas agar Hapus Massal hanya menyentuh baris yang tampil. --}}
    <span hidden wire:key="employee-page-keys-{{ md5(implode(',', $pageKeys)) }}"
        x-init="pageNiks = @js($pageKeys); selectedNiks = selectedNiks.filter(nik => pageNiks.includes(nik)); lastSelectedNik = null"></span>

    @if ($kpi !== [])
        @php
            $kpiTotal = (int) ($kpi['total'] ?? 0);
            $share = fn (int $count) => $kpiTotal > 0 ? round(($count / $kpiTotal) * 100).'%' : null;
            $karpim = \App\Models\Employee::LEVEL_KARPIM;
            $karpel = \App\Models\Employee::LEVEL_KARPEL;
            $noKpiFilter = $level === '' && $statusKhusus === '' && $statusPenugasan === '';
            $expired = (int) ($kpi['expired'] ?? 0);
            $strip = [
                'rangkap' => ['label' => 'Tugas Rangkap', 'icon' => 'fas fa-briefcase', 'count' => (int) ($kpi['rangkap'] ?? 0),
                    'on' => 'bg-info text-white border-info shadow-xs', 'off' => 'bg-white text-info-700 border-info-200 hover:bg-info-50', 'badge' => 'bg-info-100 text-info-800'],
                'sanksi' => ['label' => 'Masa Sanksi (Diperban)', 'icon' => 'fas fa-gavel', 'count' => (int) ($kpi['sanksi'] ?? 0),
                    'on' => 'bg-danger text-white border-danger shadow-xs', 'off' => 'bg-white text-danger-700 border-danger-200 hover:bg-danger-50', 'badge' => 'bg-danger-100 text-danger-800'],
                'overflow' => ['label' => 'Jabatan Overflow (Advisor)', 'icon' => 'fas fa-layer-group', 'count' => (int) ($kpi['overflow'] ?? 0),
                    'on' => 'bg-surface-700 text-white border-surface-700 shadow-xs', 'off' => 'bg-white text-surface-700 border-surface-200 hover:bg-surface-100', 'badge' => 'bg-surface-200 text-surface-800'],
            ];
        @endphp

        {{-- Summary KPI Metrics Cards (Primary Interactive Navigation & Executive Headcount Overview) --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            {{-- 1. Total Pegawai: clears the card-driven filters --}}
            <x-admin.kpi-card
                title="Total Pegawai"
                :value="$kpiTotal"
                subtitle="Pegawai aktif terdaftar"
                icon="fas fa-users"
                theme="primary"
                :active="$noKpiFilter"
                :aria-current="$noKpiFilter ? 'true' : null"
                :href="$this->kpiUrl(['level' => '', 'status_khusus' => '', 'status_penugasan' => ''])"
                wire:click.prevent="clearKpiFilters" />

            {{-- 2. Karyawan Pimpinan --}}
            <x-admin.kpi-card
                title="Karyawan Pimpinan"
                :value="(int) ($kpi['karpim'] ?? 0)"
                :badge="$share((int) ($kpi['karpim'] ?? 0))"
                subtitle="Pimpinan & Manajerial"
                icon="fas fa-user-tie"
                theme="info"
                :active="$level === $karpim"
                :aria-current="$level === $karpim ? 'true' : null"
                :href="$this->kpiUrl(['level' => $level === $karpim ? '' : $karpim])"
                wire:click.prevent="$set('level', '{{ $level === $karpim ? '' : $karpim }}')" />

            {{-- 3. Karyawan Pelaksana --}}
            <x-admin.kpi-card
                title="Karyawan Pelaksana"
                :value="(int) ($kpi['karpel'] ?? 0)"
                :badge="$share((int) ($kpi['karpel'] ?? 0))"
                subtitle="Staf & Lapangan"
                icon="fas fa-id-badge"
                theme="earth"
                :active="$level === $karpel"
                :aria-current="$level === $karpel ? 'true' : null"
                :href="$this->kpiUrl(['level' => $level === $karpel ? '' : $karpel])"
                wire:click.prevent="$set('level', '{{ $level === $karpel ? '' : $karpel }}')" />

            {{-- 4. Unit Kerja --}}
            <x-admin.kpi-card
                title="Unit Kerja"
                :value="(int) ($kpi['units'] ?? 0)"
                subtitle="Entitas Terdistribusi"
                icon="fas fa-building"
                theme="teal" />
        </div>

        {{-- Executive Governance & Special Status Quick Strip: drives the Tata Kelola filter --}}
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 bg-surface-50 border border-surface-200/80 rounded-xl text-xs shadow-2xs">
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-surface-700 flex items-center gap-1.5 mr-1">
                    <i class="fas fa-shield-alt text-primary-600" aria-hidden="true"></i> Tata Kelola & Kasus Khusus:
                </span>

                @foreach ($strip as $value => $chip)
                    @php $on = $statusKhusus === $value; @endphp
                    <button type="button" wire:key="kpi-strip-{{ $value }}"
                        wire:click.prevent="$set('statusKhusus', '{{ $on ? '' : $value }}')"
                        aria-pressed="{{ $on ? 'true' : 'false' }}"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 {{ $on ? $chip['on'] : $chip['off'] }}">
                        <i class="{{ $chip['icon'] }} text-[11px]" aria-hidden="true"></i>
                        <span>{{ $chip['label'] }}</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ $on ? 'bg-white/20 text-white' : $chip['badge'] }}">
                            {{ $chip['count'] }}
                        </span>
                        @if ($value === 'sanksi' && $expired > 0)
                            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold bg-warning-700 text-white shadow-2xs motion-safe:animate-pulse"
                                  title="{{ $expired }} pegawai masa sanksinya telah habis/kedaluwarsa">
                                {{ $expired }} kedaluwarsa
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                @if ($statusKhusus !== '')
                    <button type="button" wire:click.prevent="$set('statusKhusus', '')"
                        class="text-[11px] text-surface-500 hover:text-danger font-medium inline-flex items-center gap-1 cursor-pointer">
                        <i class="fas fa-times text-[10px]" aria-hidden="true"></i> Reset Status Khusus
                    </button>
                @endif
                <a href="{{ route('admin.mutasi.index') }}" class="text-[11px] font-semibold text-primary-700 hover:text-primary-900 hover:underline inline-flex items-center gap-1">
                    <span>Kelola di Modul Mutasi</span>
                    <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @endif

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
                {{-- Normal state (no rows selected): export of the current search, filters and sort --}}
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

                {{-- Selection state (>= 1 rows selected): selection utilities, export selected and bulk delete --}}
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

                    {{-- Export Selected --}}
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

                    {{-- Bulk Delete Trigger (Clean Subdued Outline) --}}
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
                        {{-- Dedicated Clean Checkbox Column --}}
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
