{{--
    Daftar Karyawan di atas lapisan tabel data bersama (referensi: livewire/admin/master/audit-history.blade.php).
    Kartu KPI dan strip tata kelola dirender di sini agar sorotan dan tautannya selalu mengikuti state tabel saat ini;
    klik kartu memanggil $set / clearKpiFilters (nilai divalidasi seperti filter lain), href tetap ada sebagai cadangan tanpa JS.
    Pilihan baris, Hapus massal (bilah aksi massal bersama) dan Ekspor Excel berjalan lewat lapisan tabel bersama:
    hapus massal hanya menyentuh baris terpilih di dalam hasil pencarian/filter saat ini, ekspor memakai format EmployeesExport.
    Menu aksi baris dan modal hapus satu karyawan tetap memakai state Alpine halaman admin/employees/index (confirmDelete, copyNik).
--}}
@php
    $rows = $this->rows;
    $columns = $this->columns();
    $bulkActions = $this->bulkActions();
    $pageKeys = $this->tablePageKeys();
    $selectedCount = $this->selectedCount();
    $loadingTargets = $this->tableLoadingTargets('clearKpiFilters', 'runBulkAction');
    $columnCount = $this->tableColumnCount();
    $hasActiveFilters = $this->hasActiveFilters();
@endphp
<div class="space-y-6">
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
            {{-- 1. Total Karyawan: clears the card-driven filters --}}
            <x-admin.kpi-card
                title="Total Karyawan"
                :value="$kpiTotal"
                subtitle="Karyawan aktif terdaftar"
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
                                  title="{{ $expired }} karyawan masa sanksinya telah habis/kedaluwarsa">
                                {{ $expired }} kedaluwarsa
                            </span>
                        @endif
                    </button>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
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

        <x-admin.table.header title="Daftar Karyawan" :paginator="$rows" noun="Karyawan" :export="$this->exportName() !== null" />

        <x-admin.table.bulk-bar
            :actions="$bulkActions"
            :selected="$selected"
            :select-all-matching="$selectAllMatching"
            :selected-count="$selectedCount"
            :matching-count="$rows->total()"
            :page-keys="$pageKeys" />

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse" wire:loading.attr="aria-busy" wire:target="{{ $loadingTargets }}">
                <x-admin.table.head
                    :columns="$columns"
                    :sort-field="$sortField"
                    :sort-direction="$sortDirection"
                    :selectable="$bulkActions !== []"
                    avatar-leading
                    :page-keys="$pageKeys"
                    :selected="$selected"
                    :select-all-matching="$selectAllMatching" />

                <tbody class="divide-y divide-surface-200 text-sm" wire:loading.delay.remove wire:target="{{ $loadingTargets }}">
                    @forelse ($rows as $employee)
                        <x-admin.employee-table-row :employee="$employee" :term="$search"
                            :selected="$selectAllMatching || in_array((string) $employee->nik, $selected, true)"
                            :selection-active="$selectedCount > 0"
                            wire:key="row-{{ $employee->nik }}" />
                    @empty
                        @if ($hasActiveFilters)
                            <x-admin.table.empty-state :colspan="$columnCount" icon="fas fa-users-slash" message="Tidak ada karyawan yang sesuai dengan pencarian atau filter.">
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

        <x-admin.table.pagination :paginator="$rows" noun="karyawan" :per-page-options="$this->perPageOptions()" />
    </x-admin.table.card>
</div>
