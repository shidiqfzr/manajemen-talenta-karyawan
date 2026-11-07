@extends('layouts.admin')

@section('content')
<div x-data="{
    deleteModalOpen: false,
    bulkDeleteModalOpen: false,
    deleteNik: '',
    deleteNama: '',
    deleteAction: '',
    copiedNik: null,
    isExporting: false,
    triggerExport(formId) {
        this.isExporting = true;
        const form = document.getElementById(formId);
        if (form) form.submit();
        setTimeout(() => {
            this.isExporting = false;
        }, 2500);
    },
    selectedNiks: [],
    // Keys of the visible table page, kept in sync by the employee table component on every render.
    pageNiks: [],
    lastSelectedNik: null,
    get isAllSelected() {
        return this.pageNiks.length > 0 && this.pageNiks.every(nik => this.selectedNiks.includes(nik));
    },
    toggleSelectAll(checked) {
        if (checked) {
            this.selectedNiks = Array.from(new Set([...this.selectedNiks, ...this.pageNiks]));
        } else {
            this.selectedNiks = this.selectedNiks.filter(nik => !this.pageNiks.includes(nik));
        }
        this.lastSelectedNik = null;
    },
    toggleSelect(nik, event = null) {
        if (event && event.shiftKey && this.lastSelectedNik && this.pageNiks.includes(this.lastSelectedNik)) {
            const startIdx = this.pageNiks.indexOf(this.lastSelectedNik);
            const endIdx = this.pageNiks.indexOf(nik);
            const minIdx = Math.min(startIdx, endIdx);
            const maxIdx = Math.max(startIdx, endIdx);
            const rangeNiks = this.pageNiks.slice(minIdx, maxIdx + 1);
            this.selectedNiks = Array.from(new Set([...this.selectedNiks, ...rangeNiks]));
            this.lastSelectedNik = nik;
            return;
        }

        if (this.selectedNiks.includes(nik)) {
            this.selectedNiks = this.selectedNiks.filter(n => n !== nik);
        } else {
            this.selectedNiks.push(nik);
        }
        this.lastSelectedNik = nik;
    },
    handleRowClick(nik, event, showUrl) {
        // 1. Modifier keys (Ctrl/Cmd or Shift): toggle or range-select
        if (event.ctrlKey || event.metaKey || event.shiftKey) {
            event.preventDefault();
            this.toggleSelect(nik, event);
            return;
        }

        // 2. Selection mode is active (>= 1 items): click anywhere toggles this row
        if (this.selectedNiks.length > 0) {
            this.toggleSelect(nik, event);
            return;
        }

        // 3. Normal mode: navigate to profile
        window.location.href = showUrl;
    },
    deselectAll() {
        this.selectedNiks = [];
        this.lastSelectedNik = null;
    },
    copyNik(nik) {
        navigator.clipboard.writeText(nik);
        this.copiedNik = nik;
        setTimeout(() => { this.copiedNik = null; }, 2000);
    },
    confirmDelete(nik, nama, deleteUrl) {
        this.deleteNik = nik;
        this.deleteNama = nama;
        this.deleteAction = deleteUrl;
        this.deleteModalOpen = true;
    }
}" class="space-y-6">

    <!-- Page Header & Action Bar -->
    <x-admin.module-header title="Manajemen Karyawan" subtitle="Kelola data induk karyawan, struktur jabatan, formasi, dan rekam jejak talenta." :breadcrumbs="['Core HR', 'Manajemen Karyawan']">
        <!-- Import Page Navigation Button (Enterprise Workday / SAP Multi-Step Standard) -->
        <a href="{{ route('admin.employees.import.page') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 hover:text-surface-900 text-xs sm:text-sm font-semibold transition shadow-xs cursor-pointer">
            <i class="fas fa-file-arrow-up text-primary-600 text-xs" aria-hidden="true"></i>
            <span>Import Excel</span>
        </a>

        <!-- Tambah Karyawan (Primary CTA) -->
        <a href="{{ route('admin.employees.create') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow">
            <i class="fas fa-plus text-xs" aria-hidden="true"></i>
            <span>Tambah Karyawan</span>
        </a>
    </x-admin.module-header>

    <!-- Import Result Banner (Shown if an import was recently executed) -->
    @if(session('import_summary'))
        @php $summary = session('import_summary'); @endphp
        <div role="status" x-data="{ showErrors: false }" class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm space-y-3 animate-fade-in">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $summary['has_errors'] ? 'bg-warning-50 text-warning border border-warning-200' : 'bg-primary-50 text-primary-700 border border-primary-200' }} flex items-center justify-center text-lg shrink-0">
                        <i class="{{ $summary['has_errors'] ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle' }}" aria-hidden="true"></i>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-surface-900">
                            {{ $summary['has_errors'] ? 'Import Selesai dengan Beberapa Catatan' : 'Import Data Berhasil Diproses' }}
                        </h4>
                        <p class="text-xs text-surface-500 mt-0.5">
                            Total diproses: <strong class="text-surface-800">{{ $summary['total_processed'] }} baris</strong>
                        </p>
                    </div>
                </div>

                <!-- Status Badges -->
                <div class="flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-primary-50 text-primary-800 border border-primary-200">
                        <i class="fas fa-plus-circle text-[10px]" aria-hidden="true"></i>
                        {{ $summary['created'] }} Baru
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-surface-100 text-surface-800 border border-surface-200">
                        <i class="fas fa-rotate text-[10px]" aria-hidden="true"></i>
                        {{ $summary['updated'] }} Diperbarui
                    </span>
                    @if($summary['skipped'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-warning-50 text-warning-800 border border-warning-200">
                            <i class="fas fa-triangle-exclamation text-[10px]" aria-hidden="true"></i>
                            {{ $summary['skipped'] }} Dilewati
                        </span>
                    @endif
                </div>
            </div>

            <!-- Error List Toggle if errors exist -->
            @if(!empty($summary['errors']))
                <div class="pt-2 border-t border-surface-200">
                    <button type="button" @click="showErrors = !showErrors"
                        class="text-xs font-semibold text-danger hover:underline inline-flex items-center gap-1.5">
                        <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showErrors }" aria-hidden="true"></i>
                        <span x-text="showErrors ? 'Sembunyikan Detail Kendala Baris' : 'Lihat Detail Baris yang Mengalami Kendala (' + {{ count($summary['errors']) }} + ')'"></span>
                    </button>

                    <div x-show="showErrors" x-cloak class="mt-2.5 space-y-2 bg-danger-50/60 border border-danger-200/80 rounded-lg p-3 text-xs text-danger-800 max-h-56 overflow-y-auto">
                        @foreach($summary['errors'] as $errorItem)
                            <div class="flex items-start gap-2.5 py-1.5 border-b border-danger-100 last:border-b-0">
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold text-danger-700 bg-danger-100 rounded shrink-0">
                                    Baris {{ is_array($errorItem) ? ($errorItem['row'] ?? '?') : '?' }}
                                </span>
                                <div class="flex-1 min-w-0">
                                    @if(is_array($errorItem) && !empty($errorItem['nik']) && $errorItem['nik'] !== 'Unknown')
                                        <span class="font-mono font-semibold text-surface-800">[{{ $errorItem['nik'] }}]</span>
                                    @endif
                                    <span class="text-surface-700 leading-relaxed">
                                        @if(is_array($errorItem) && isset($errorItem['errors']) && is_array($errorItem['errors']))
                                            {{ implode('; ', $errorItem['errors']) }}
                                        @elseif(is_array($errorItem) && isset($errorItem['errors']))
                                            {{ $errorItem['errors'] }}
                                        @elseif(is_string($errorItem))
                                            {{ $errorItem }}
                                        @else
                                            Kesalahan format baris data.
                                        @endif
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    @endif

    @if(isset($expiredSanksiEmployees) && $expiredSanksiEmployees->isNotEmpty())
        <div class="bg-danger-50 border border-danger-200 rounded-xl p-4 shadow-sm animate-fade-in relative overflow-hidden">
            <!-- Decorative background pattern -->
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-danger-100 rounded-full opacity-50 pointer-events-none"></div>

            <div class="relative flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-danger-100 text-danger flex items-center justify-center text-lg shrink-0 border border-danger-200 shadow-inner">
                    <i class="fas fa-exclamation-circle motion-safe:animate-pulse" aria-hidden="true"></i>
                </div>
                <div class="space-y-1.5 flex-1">
                    <h4 class="text-sm font-bold text-danger-800 uppercase tracking-wide flex items-center gap-2">
                        Peringatan: Masa Sanksi Kedaluwarsa
                        <span class="bg-danger text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-sm">{{ $expiredSanksiEmployees->count() }}</span>
                    </h4>
                    <p class="text-xs text-danger-800 leading-relaxed max-w-4xl">
                        Terdapat <strong class="text-danger-800 font-extrabold">{{ $expiredSanksiEmployees->count() }} karyawan</strong> yang masa sanksi (Diperban)-nya telah berakhir hari ini atau sebelumnya. Mohon segera evaluasi dan terbitkan Mutasi Pencabutan Sanksi untuk memulihkan status mereka.
                    </p>

                    <!-- Expandable List -->
                    <div x-data="{ showList: false }" class="mt-3">
                        <button type="button" @click="showList = !showList"
                            class="text-xs font-semibold text-danger-700 hover:text-danger-800 hover:underline inline-flex items-center gap-1.5 transition-colors">
                            <span x-text="showList ? 'Sembunyikan Daftar Karyawan' : 'Lihat Daftar Karyawan'"></span>
                            <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showList }" aria-hidden="true"></i>
                        </button>

                        <div x-show="showList" x-collapse x-cloak class="mt-3 bg-white/60 border border-danger-200 rounded-lg p-3 backdrop-blur-sm shadow-inner">
                            <div class="max-h-56 overflow-y-auto space-y-2 pr-2" style="scrollbar-width: thin; scrollbar-color: #fca5a5 transparent;">
                                @foreach($expiredSanksiEmployees as $emp)
                                    <div class="flex items-center justify-between gap-4 py-2 border-b border-danger-100 last:border-0 hover:bg-danger-50/50 transition-colors px-2 rounded-md">
                                        <div>
                                            <a href="{{ route('admin.employees.show', $emp->nik) }}" class="text-xs font-bold text-danger-800 hover:underline hover:text-danger-700">
                                                {{ $emp->nama }}
                                            </a>
                                            <p class="text-[10px] text-danger-700 mt-0.5 font-medium"><i class="fas fa-id-badge mr-1" aria-hidden="true"></i>{{ $emp->nik }} <span class="mx-1 text-danger-200">|</span> <i class="fas fa-briefcase mr-1" aria-hidden="true"></i>{{ $emp->jabatan }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-[10px] font-bold text-danger-800 bg-danger-100/80 px-2 py-1 rounded border border-danger-200 shadow-sm flex items-center gap-1.5">
                                                <i class="fas fa-calendar-times text-danger" aria-hidden="true"></i>
                                                Tgl. Berakhir: {{ \Carbon\Carbon::parse($emp->tanggal_berakhir_sanksi)->format('d M Y') }}
                                            </span>
                                            <a href="{{ route('admin.mutasi.create', ['nik' => $emp->nik]) }}" class="inline-flex mt-1 text-[10px] font-semibold text-danger hover:text-danger-800 hover:underline">
                                                Cabut Sanksi (Mutasi) &rarr;
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Summary KPI Metrics Cards (Primary Interactive Navigation & Executive Headcount Overview) -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <!-- 1. Total Pegawai (Filter Reset to All) -->
        <x-admin.kpi-card
            title="Total Pegawai"
            :value="$totalEmployees"
            subtitle="Pegawai aktif terdaftar"
            icon="fas fa-users"
            theme="primary"
            :active="!request('level') && !request('status_khusus') && !request('status_penugasan')"
            :href="route('admin.employees.index')" />

        <!-- 2. Karyawan Pimpinan -->
        <x-admin.kpi-card
            title="Karyawan Pimpinan"
            :value="$karpimCount"
            :badge="$totalEmployees > 0 ? round(($karpimCount / $totalEmployees) * 100) . '%' : null"
            subtitle="Pimpinan & Manajerial"
            icon="fas fa-user-tie"
            theme="info"
            :active="request('level') === 'Karpim'"
            :href="request()->fullUrlWithQuery(['level' => request('level') === 'Karpim' ? null : 'Karpim'])" />

        <!-- 3. Karyawan Pelaksana -->
        <x-admin.kpi-card
            title="Karyawan Pelaksana"
            :value="$pelaksanaCount"
            :badge="$totalEmployees > 0 ? round(($pelaksanaCount / $totalEmployees) * 100) . '%' : null"
            subtitle="Staf & Lapangan"
            icon="fas fa-id-badge"
            theme="earth"
            :active="request('level') === 'Pelaksana'"
            :href="request()->fullUrlWithQuery(['level' => request('level') === 'Pelaksana' ? null : 'Pelaksana'])" />

        <!-- 4. Unit Kerja -->
        <x-admin.kpi-card
            title="Unit Kerja"
            :value="$totalUnits"
            subtitle="Entitas Terdistribusi"
            icon="fas fa-building"
            theme="teal" />
    </div>

    <!-- Executive Governance & Special Status Quick Strip -->
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 bg-surface-50 border border-surface-200/80 rounded-xl text-xs shadow-2xs">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-bold text-surface-700 flex items-center gap-1.5 mr-1">
                <i class="fas fa-shield-alt text-primary-600" aria-hidden="true"></i> Tata Kelola & Kasus Khusus:
            </span>

            <!-- Chip 1: Tugas Rangkap -->
            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => request('status_khusus') === 'rangkap' ? null : 'rangkap']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer {{ request('status_khusus') === 'rangkap' ? 'bg-info text-white border-info shadow-xs' : 'bg-white text-info-700 border-info-200 hover:bg-info-50' }}">
                <i class="fas fa-briefcase text-[11px]" aria-hidden="true"></i>
                <span>Tugas Rangkap</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ request('status_khusus') === 'rangkap' ? 'bg-white/20 text-white' : 'bg-info-100 text-info-800' }}">
                    {{ $penugasanSementaraCount }}
                </span>
            </a>

            <!-- Chip 2: Masa Sanksi (Diperban) -->
            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => request('status_khusus') === 'sanksi' ? null : 'sanksi']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer {{ request('status_khusus') === 'sanksi' ? 'bg-danger text-white border-danger shadow-xs' : 'bg-white text-danger-700 border-danger-200 hover:bg-danger-50' }}">
                <i class="fas fa-gavel text-[11px]" aria-hidden="true"></i>
                <span>Masa Sanksi (Diperban)</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ request('status_khusus') === 'sanksi' ? 'bg-white/20 text-white' : 'bg-danger-100 text-danger-800' }}">
                    {{ $sanksiAktifCount }}
                </span>
                @if($expiredSanksiEmployees->isNotEmpty())
                    <span class="px-1.5 py-0.2 rounded-full text-[10px] font-extrabold bg-warning-700 text-white shadow-2xs motion-safe:animate-pulse"
                          title="{{ $expiredSanksiEmployees->count() }} pegawai masa sanksinya telah habis/kedaluwarsa">
                        {{ $expiredSanksiEmployees->count() }} kedaluwarsa
                    </span>
                @endif
            </a>

            <!-- Chip 3: Jabatan Overflow (Advisor) -->
            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => request('status_khusus') === 'overflow' ? null : 'overflow']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer {{ request('status_khusus') === 'overflow' ? 'bg-surface-700 text-white border-surface-700 shadow-xs' : 'bg-white text-surface-700 border-surface-200 hover:bg-surface-100' }}">
                <i class="fas fa-layer-group text-[11px]" aria-hidden="true"></i>
                <span>Jabatan Overflow (Advisor)</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ request('status_khusus') === 'overflow' ? 'bg-white/20 text-white' : 'bg-surface-200 text-surface-800' }}">
                    {{ $overflowCount }}
                </span>
            </a>
        </div>

        <div class="flex items-center gap-3">
            @if(request('status_khusus'))
                <a href="{{ request()->fullUrlWithQuery(['status_khusus' => null]) }}"
                   class="text-[11px] text-surface-500 hover:text-danger font-medium inline-flex items-center gap-1">
                    <i class="fas fa-times text-[10px]" aria-hidden="true"></i> Reset Status Khusus
                </a>
            @endif
            <a href="{{ route('admin.mutasi.index') }}" class="text-[11px] font-semibold text-primary-700 hover:text-primary-900 hover:underline inline-flex items-center gap-1">
                <span>Kelola di Modul Mutasi</span>
                <i class="fas fa-arrow-right text-[10px]" aria-hidden="true"></i>
            </a>
        </div>
    </div>

    {{-- Direktori Pegawai: pencarian, filter, urutan, dan paginasi di komponen tabel bersama (App\Livewire\Admin\Employees\EmployeeTable).
         Kartu KPI dan strip tata kelola di atas mengarah ke ?level= / ?status_khusus= yang dibaca komponen saat halaman dimuat. --}}
    <livewire:admin.employees.employee-table />

    <div class="sr-only" role="status" aria-live="polite" aria-atomic="true"
         x-text="selectedNiks.length > 0 ? selectedNiks.length + ' pegawai terpilih' : ''"></div>

    {{-- MODAL: Delete Confirmation Modal (Destructive Action - DESIGN.md 6.5) --}}
    <div x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="delete-dialog-title" role="dialog" aria-modal="true" x-trap.noscroll="deleteModalOpen" tabindex="-1">
        
        <!-- Backdrop -->
        <div x-show="deleteModalOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity"
            @click="deleteModalOpen = false"></div>

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="deleteModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-md border border-surface-200">
                
                <div class="p-6">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-2xl bg-danger-100 text-danger flex items-center justify-center text-xl shrink-0 border border-danger-200">
                            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        </div>
                        <div class="space-y-1.5 flex-1">
                            <h3 class="text-lg font-bold text-surface-900" id="delete-dialog-title">Hapus Data Karyawan</h3>
                            <p class="text-xs sm:text-sm text-surface-600 leading-relaxed">
                                Apakah Anda yakin ingin menghapus data pegawai <strong class="text-surface-900 font-semibold" x-text="deleteNama"></strong> (NIK: <span class="font-mono text-surface-700 font-semibold" x-text="deleteNik"></span>)?
                            </p>
                            <p class="text-xs text-danger font-medium mt-2">
                                <i class="fas fa-circle-exclamation mr-1" aria-hidden="true"></i> Data riwayat mutasi, pelatihan, dan evaluasi terkait akan ikut terhapus permanen. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2.5">
                    <button type="button" autofocus @click="deleteModalOpen = false"
                        class="px-4 py-2.5 rounded-lg border border-surface-300 bg-white text-surface-700 hover:bg-surface-100 text-xs sm:text-sm font-medium transition shadow-xs">
                        Batal
                    </button>
                    <form :action="deleteAction" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-danger hover:bg-danger-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm">
                            <i class="fas fa-trash-alt text-xs" aria-hidden="true"></i>
                            <span>Ya, Hapus Data</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Floating Bulk Action Bar (Linear / Stripe Standard - Dark Slate Glassmorphism) -->
    <div x-show="selectedNiks.length > 0" x-cloak
        x-transition:enter="transition cubic-bezier(0.16, 1, 0.3, 1) duration-300"
        x-transition:enter-start="opacity-0 translate-y-8 scale-95"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-8 scale-95"
        @keydown.window.escape="if (selectedNiks.length > 0 && !bulkDeleteModalOpen) deselectAll()"
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-surface-900/95 backdrop-blur-md text-white px-4 py-2.5 rounded-2xl shadow-2xl flex items-center gap-3.5 border border-surface-700/80 max-w-[95vw]">
        
        <!-- Counter Badge & Deselect -->
        <div class="flex items-center gap-2.5 pr-2 border-r border-surface-700/80">
            <span class="w-6 h-6 rounded-full bg-primary-500 text-surface-950 font-bold font-mono text-xs flex items-center justify-center shrink-0 shadow-xs"
                x-text="selectedNiks.length"></span>
            <span class="text-xs font-semibold text-surface-200 whitespace-nowrap"><span x-text="selectedNiks.length"></span> Dipilih</span>
            <button type="button" @click="deselectAll()"
                class="text-xs text-surface-500 hover:text-white transition px-1.5 py-0.5 rounded-md hover:bg-surface-800 cursor-pointer flex items-center gap-1"
                title="Batalkan Pilihan (Esc)">
                <i class="fas fa-times text-[11px]" aria-hidden="true"></i>
                <kbd class="text-[10px] bg-surface-800 text-surface-500 px-1 py-0.2 rounded font-mono border border-surface-700 hidden sm:inline">Esc</kbd>
            </button>
        </div>

        <!-- Action: Export Selected to Excel -->
        <form action="{{ route('admin.employees.export') }}" method="GET" id="bottomExportSelectedForm" class="inline">
            <template x-for="nik in selectedNiks" :key="'export-' + nik">
                <input type="hidden" name="niks[]" :value="nik">
            </template>
            <button type="button" @click="triggerExport('bottomExportSelectedForm')" :disabled="isExporting"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-xs font-semibold shadow-xs transition active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                <template x-if="!isExporting">
                    <i class="fas fa-file-excel text-xs" aria-hidden="true"></i>
                </template>
                <template x-if="isExporting">
                    <i class="fas fa-circle-notch fa-spin text-xs" aria-hidden="true"></i>
                </template>
                <span>Export Excel</span>
            </button>
        </form>

        <!-- Action: Bulk Delete Modal Trigger (Protected Dangerous State) -->
        <button type="button" @click="bulkDeleteModalOpen = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-surface-800 hover:bg-danger-800/40 text-danger hover:text-danger-200 border border-surface-700 hover:border-danger-800/60 text-xs font-semibold transition active:scale-95 cursor-pointer">
            <i class="fas fa-trash-alt text-xs" aria-hidden="true"></i>
            <span>Hapus</span>
        </button>
    </div>

    <!-- Bulk Delete Confirmation Modal (Enterprise Destructive Safety) -->
    <div x-show="bulkDeleteModalOpen" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="bulk-delete-dialog-title" role="dialog" aria-modal="true" x-trap.noscroll="bulkDeleteModalOpen" tabindex="-1">
        
        <div x-show="bulkDeleteModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-surface-900/60 backdrop-blur-xs transition-opacity"
            @click="bulkDeleteModalOpen = false"></div>

        <div class="flex min-h-screen items-center justify-center p-4">
            <div x-show="bulkDeleteModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-surface-200 overflow-hidden text-left"
                @click.stop>
                
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-danger-50 text-danger border border-danger-200 flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                    </div>
                    <div class="flex-1 space-y-2">
                        <h3 class="text-lg font-bold text-surface-900" id="bulk-delete-dialog-title">
                            Hapus Massal Pegawai Terpilih
                        </h3>
                        <p class="text-xs sm:text-sm text-surface-600 leading-relaxed">
                            Anda akan menghapus <strong class="text-surface-900 font-semibold" x-text="selectedNiks.length"></strong> data pegawai yang dicentang. Seluruh berkas foto profil, data riwayat penempatan, evaluasi, dan pelatihan yang melekat akan ikut terhapus secara permanen.
                        </p>
                        
                        <!-- Selected NIK list preview -->
                        <div class="mt-3 p-2.5 bg-surface-50 rounded-xl border border-surface-200 max-h-28 overflow-y-auto">
                            <span class="text-[11px] font-semibold text-surface-500 block mb-1">NIK Terpilih:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="nik in selectedNiks" :key="'badge-' + nik">
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-surface-200 font-mono text-[11px] text-surface-700 shadow-2xs"
                                        x-text="nik"></span>
                                </template>
                            </div>
                        </div>

                        <p class="text-xs text-danger font-medium mt-2">
                            <i class="fas fa-circle-exclamation mr-1" aria-hidden="true"></i> Tindakan penghapusan massal ini tidak dapat dibatalkan (Irreversible).
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-surface-100 flex items-center justify-end gap-2.5">
                    <button type="button" autofocus @click="bulkDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl border border-surface-200 bg-white text-surface-700 hover:bg-surface-50 text-xs font-semibold transition cursor-pointer shadow-2xs">
                        Batal
                    </button>
                    <form action="{{ route('admin.employees.bulkDestroy') }}" method="POST" class="inline">
                        @csrf
                        <template x-for="nik in selectedNiks" :key="'delete-' + nik">
                            <input type="hidden" name="niks[]" :value="nik">
                        </template>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-danger hover:bg-danger-700 text-white text-xs font-semibold transition shadow-xs cursor-pointer">
                            <i class="fas fa-trash-alt text-xs" aria-hidden="true"></i>
                            <span>Ya, Hapus <span x-text="selectedNiks.length"></span> Pegawai</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<x-toast />
@endsection
