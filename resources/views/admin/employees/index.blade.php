@extends('layouts.admin')

@section('content')
<div x-data="{
    importModalOpen: false,
    deleteModalOpen: false,
    bulkDeleteModalOpen: false,
    importModalTab: 'upload',
    specsFilter: 'all',
    showAdvancedFilters: {{ request()->hasAny(['unit_kerja', 'jabatan', 'rm_level', 'tahun_masuk']) ? 'true' : 'false' }},
    deleteNik: '',
    deleteNama: '',
    deleteAction: '',
    copiedNik: null,
    selectedNiks: [],
    pageNiks: {{ json_encode($employees->pluck('nik')->toArray()) }},
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
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <!-- Breadcrumbs -->
            <nav class="flex items-center gap-2 text-xs font-medium text-surface-500 mb-1.5" aria-label="Breadcrumb">
                <span>Core HR</span>
                <i class="fas fa-chevron-right text-[10px] text-surface-400"></i>
                <span class="text-primary-700 font-semibold">Manajemen Karyawan</span>
            </nav>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-surface-900">Manajemen Karyawan</h1>
            <p class="text-sm text-surface-500 mt-0.5">Kelola data induk karyawan, struktur jabatan, formasi, dan rekam jejak talenta.</p>
        </div>

        <!-- Clean Header Actions: Import Modal & Primary CTA -->
        <div class="flex items-center gap-2.5 shrink-0">
            <!-- Import Modal Trigger Button -->
            <button type="button" @click="importModalOpen = true"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 text-xs sm:text-sm font-semibold transition shadow-xs cursor-pointer">
                <i class="fas fa-file-arrow-up text-emerald-600 text-xs"></i>
                <span>Import Excel</span>
            </button>

            <!-- Tambah Karyawan (Primary CTA) -->
            <a href="{{ route('admin.employees.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow">
                <i class="fas fa-plus text-xs"></i>
                <span>Tambah Karyawan</span>
            </a>
        </div>
    </div>

    <!-- Import Result Banner (Shown if an import was recently executed) -->
    @if(session('import_summary'))
        @php $summary = session('import_summary'); @endphp
        <div x-data="{ showErrors: false }" class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm space-y-3 animate-fade-in">
            <div class="flex items-start justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl {{ $summary['has_errors'] ? 'bg-amber-50 text-warning border border-amber-200' : 'bg-primary-50 text-primary-700 border border-primary-200' }} flex items-center justify-center text-lg shrink-0">
                        <i class="{{ $summary['has_errors'] ? 'fas fa-exclamation-triangle' : 'fas fa-check-circle' }}"></i>
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
                        <i class="fas fa-plus-circle text-[10px]"></i>
                        {{ $summary['created'] }} Baru
                    </span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-surface-100 text-surface-800 border border-surface-200">
                        <i class="fas fa-rotate text-[10px]"></i>
                        {{ $summary['updated'] }} Diperbarui
                    </span>
                    @if($summary['skipped'] > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                            <i class="fas fa-triangle-exclamation text-[10px]"></i>
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
                        <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showErrors }"></i>
                        <span x-text="showErrors ? 'Sembunyikan Detail Kendala Baris' : 'Lihat Detail Baris yang Mengalami Kendala (' + {{ count($summary['errors']) }} + ')'"></span>
                    </button>

                    <div x-show="showErrors" x-cloak class="mt-2.5 space-y-2 bg-red-50/60 border border-red-200/80 rounded-lg p-3 text-xs text-red-900 max-h-56 overflow-y-auto">
                        @foreach($summary['errors'] as $errorItem)
                            <div class="flex items-start gap-2.5 py-1.5 border-b border-red-100 last:border-b-0">
                                <span class="inline-flex items-center justify-center px-1.5 py-0.5 text-[10px] font-bold text-red-700 bg-red-100 rounded shrink-0">
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
        <div class="bg-red-50 border border-red-200 rounded-xl p-4 shadow-sm animate-fade-in relative overflow-hidden">
            <!-- Decorative background pattern -->
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-red-100 rounded-full opacity-50 pointer-events-none"></div>

            <div class="relative flex items-start gap-4">
                <div class="w-10 h-10 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-lg shrink-0 border border-red-200 shadow-inner">
                    <i class="fas fa-exclamation-circle animate-pulse"></i>
                </div>
                <div class="space-y-1.5 flex-1">
                    <h4 class="text-sm font-bold text-red-900 uppercase tracking-wide flex items-center gap-2">
                        Peringatan: Masa Sanksi Kedaluwarsa
                        <span class="bg-red-600 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full shadow-sm">{{ $expiredSanksiEmployees->count() }}</span>
                    </h4>
                    <p class="text-xs text-red-800 leading-relaxed max-w-4xl">
                        Terdapat <strong class="text-red-900 font-extrabold">{{ $expiredSanksiEmployees->count() }} karyawan</strong> yang masa sanksi (Diperban)-nya telah berakhir hari ini atau sebelumnya. Mohon segera evaluasi dan terbitkan Mutasi Pencabutan Sanksi untuk memulihkan status mereka.
                    </p>

                    <!-- Expandable List -->
                    <div x-data="{ showList: false }" class="mt-3">
                        <button type="button" @click="showList = !showList"
                            class="text-xs font-semibold text-red-700 hover:text-red-900 hover:underline inline-flex items-center gap-1.5 transition-colors">
                            <span x-text="showList ? 'Sembunyikan Daftar Karyawan' : 'Lihat Daftar Karyawan'"></span>
                            <i class="fas fa-chevron-down text-[10px] transition-transform duration-200" :class="{ 'rotate-180': showList }"></i>
                        </button>

                        <div x-show="showList" x-collapse x-cloak class="mt-3 bg-white/60 border border-red-200 rounded-lg p-3 backdrop-blur-sm shadow-inner">
                            <div class="max-h-56 overflow-y-auto space-y-2 pr-2" style="scrollbar-width: thin; scrollbar-color: #fca5a5 transparent;">
                                @foreach($expiredSanksiEmployees as $emp)
                                    <div class="flex items-center justify-between gap-4 py-2 border-b border-red-100 last:border-0 hover:bg-red-50/50 transition-colors px-2 rounded-md">
                                        <div>
                                            <a href="{{ route('admin.employees.show', $emp->nik) }}" class="text-xs font-bold text-red-900 hover:underline hover:text-red-700">
                                                {{ $emp->nama }}
                                            </a>
                                            <p class="text-[10px] text-red-700 mt-0.5 font-medium"><i class="fas fa-id-badge mr-1"></i>{{ $emp->nik }} <span class="mx-1 text-red-300">|</span> <i class="fas fa-briefcase mr-1"></i>{{ $emp->jabatan }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <span class="text-[10px] font-bold text-red-900 bg-red-100/80 px-2 py-1 rounded border border-red-200 shadow-sm flex items-center gap-1.5">
                                                <i class="fas fa-calendar-times text-red-600"></i>
                                                Tgl. Berakhir: {{ \Carbon\Carbon::parse($emp->tanggal_berakhir_sanksi)->format('d M Y') }}
                                            </span>
                                            <a href="{{ route('admin.mutasi.create', ['nik' => $emp->nik]) }}" class="inline-flex mt-1 text-[10px] font-semibold text-red-600 hover:text-red-800 hover:underline">
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
            theme="sky"
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
    <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-2.5 bg-slate-50 border border-slate-200/80 rounded-xl text-xs shadow-2xs">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-bold text-slate-700 flex items-center gap-1.5 mr-1">
                <i class="fas fa-shield-alt text-emerald-600"></i> Tata Kelola & Kasus Khusus:
            </span>

            <!-- Chip 1: Tugas Rangkap -->
            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => request('status_khusus') === 'rangkap' ? null : 'rangkap']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer {{ request('status_khusus') === 'rangkap' ? 'bg-purple-600 text-white border-purple-600 shadow-xs' : 'bg-white text-purple-700 border-purple-200 hover:bg-purple-50' }}">
                <i class="fas fa-briefcase text-[11px]"></i>
                <span>Tugas Rangkap</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ request('status_khusus') === 'rangkap' ? 'bg-white/20 text-white' : 'bg-purple-100 text-purple-800' }}">
                    {{ $penugasanSementaraCount }}
                </span>
            </a>

            <!-- Chip 2: Masa Sanksi (Diperban) -->
            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => request('status_khusus') === 'sanksi' ? null : 'sanksi']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer {{ request('status_khusus') === 'sanksi' ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-white text-red-700 border-red-200 hover:bg-red-50' }}">
                <i class="fas fa-gavel text-[11px]"></i>
                <span>Masa Sanksi (Diperban)</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ request('status_khusus') === 'sanksi' ? 'bg-white/20 text-white' : 'bg-red-100 text-red-800' }}">
                    {{ $sanksiAktifCount }}
                </span>
                @if($expiredSanksiEmployees->isNotEmpty())
                    <span class="px-1.5 py-0.2 rounded-full text-[9px] font-extrabold bg-amber-500 text-white shadow-2xs animate-pulse"
                          title="{{ $expiredSanksiEmployees->count() }} pegawai masa sanksinya telah habis/kedaluwarsa">
                        {{ $expiredSanksiEmployees->count() }} kedaluwarsa
                    </span>
                @endif
            </a>

            <!-- Chip 3: Jabatan Overflow (Advisor) -->
            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => request('status_khusus') === 'overflow' ? null : 'overflow']) }}"
               class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-xs font-semibold transition cursor-pointer {{ request('status_khusus') === 'overflow' ? 'bg-slate-700 text-white border-slate-700 shadow-xs' : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100' }}">
                <i class="fas fa-layer-group text-[11px]"></i>
                <span>Jabatan Overflow (Advisor)</span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-bold {{ request('status_khusus') === 'overflow' ? 'bg-white/20 text-white' : 'bg-slate-200 text-slate-800' }}">
                    {{ $overflowCount }}
                </span>
            </a>
        </div>

        <div class="flex items-center gap-3">
            @if(request('status_khusus'))
                <a href="{{ request()->fullUrlWithQuery(['status_khusus' => null]) }}"
                   class="text-[11px] text-slate-500 hover:text-red-600 font-medium inline-flex items-center gap-1">
                    <i class="fas fa-times text-[9px]"></i> Reset Status Khusus
                </a>
            @endif
            <a href="{{ route('admin.mutasi.index') }}" class="text-[11px] font-semibold text-emerald-700 hover:text-emerald-900 hover:underline inline-flex items-center gap-1">
                <span>Kelola di Modul Mutasi</span>
                <i class="fas fa-arrow-right text-[10px]"></i>
            </a>
        </div>
    </div>

    <!-- Main Card: Filter Bar & Employee Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        
        <!-- Filter Toolbar -->
        <div class="p-5 border-b border-slate-200/80 bg-slate-50/50 space-y-4">
            <form method="GET" action="{{ route('admin.employees.index') }}" id="filterForm" class="space-y-4">
                <!-- Keep level, status_penugasan & status_khusus hidden if already selected from KPI cards / quick strip -->
                @if(request('level'))
                    <input type="hidden" name="level" value="{{ request('level') }}">
                @endif
                @if(request('status_penugasan') && !request()->has('status_penugasan_select'))
                    <input type="hidden" name="status_penugasan" value="{{ request('status_penugasan') }}">
                @endif
                @if(request('status_khusus'))
                    <input type="hidden" name="status_khusus" value="{{ request('status_khusus') }}">
                @endif

                <!-- Unified 1-Row Toolbar: Search Input + Advanced Filter Toggle + Reset Filter -->
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                    <!-- Search Input (Reusable Enterprise Component: NIK, Nama, Jabatan, Unit Kerja with '/' shortcut) -->
                    <x-admin.search-input
                        name="search"
                        id="employeeSearchInput"
                        placeholder="Cari NIK, Nama, Jabatan, atau Unit Kerja..." />

                    <!-- Toggle Button for Advanced Dropdown Filters -->
                    <button type="button" @click="showAdvancedFilters = !showAdvancedFilters"
                        class="inline-flex items-center justify-center gap-2 px-3.5 py-2.5 rounded-xl border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs shrink-0 cursor-pointer">
                        <i class="fas fa-sliders text-emerald-600 text-xs"></i>
                        <span>Filter Lanjutan</span>
                        @php
                            $advCount = count(array_filter([
                                request('unit_kerja'),
                                request('jabatan'),
                                request('rm_level'),
                                request('tahun_masuk'),
                            ]));
                        @endphp
                        @if($advCount > 0)
                            <span class="px-1.5 py-0.2 rounded-full bg-emerald-600 text-white text-[10px] font-bold">
                                {{ $advCount }}
                            </span>
                        @endif
                        <i class="fas text-[10px] text-slate-400" :class="showAdvancedFilters ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>

                    <!-- Reset Filter Button (Shown when filters active) -->
                    @if(request()->hasAny(['search', 'unit_kerja', 'jabatan', 'level', 'rm_level', 'golongan', 'tahun_masuk', 'status_penugasan']))
                        <a href="{{ route('admin.employees.index') }}"
                            class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 bg-white border border-slate-200 text-slate-600 hover:text-danger hover:border-red-200 hover:bg-red-50/50 rounded-xl text-xs font-semibold transition shadow-2xs shrink-0 cursor-pointer"
                            title="Reset semua filter">
                            <i class="fas fa-rotate-left text-xs"></i>
                            <span class="hidden sm:inline">Reset Filter</span>
                        </a>
                    @endif
                </div>

                <!-- Collapsible Advanced Filters Panel (4 Core Essential Filters in 1 Symmetrical Row) -->
                <div x-show="showAdvancedFilters" x-cloak
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 -translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0"
                    class="pt-4 mt-1 border-t border-slate-200/80 space-y-3.5">

                    <!-- Symmetrical 4-Column Grid for High-Frequency Core Filters -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
                        <!-- 1. Unit Kerja -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                <i class="fas fa-building text-slate-400 text-[10px] mr-1"></i> Unit Kerja
                            </label>
                            <x-admin.searchable-select
                                name="unit_kerja"
                                id="filterUnitKerja"
                                :options="$units"
                                :selected="request('unit_kerja')"
                                placeholder="Semua Unit Kerja"
                                searchPlaceholder="Cari unit kerja..."
                                icon="fas fa-building"
                                :hasAllOption="true"
                                allOptionText="Semua Unit Kerja"
                                emptyText="Unit kerja tidak ditemukan" />
                        </div>

                        <!-- 2. Jabatan -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                <i class="fas fa-briefcase text-slate-400 text-[10px] mr-1"></i> Jabatan
                            </label>
                            <x-admin.searchable-select
                                name="jabatan"
                                id="filterJabatan"
                                :options="$jabatans"
                                :selected="request('jabatan')"
                                placeholder="Semua Jabatan"
                                searchPlaceholder="Cari nama jabatan..."
                                icon="fas fa-briefcase"
                                :hasAllOption="true"
                                allOptionText="Semua Jabatan"
                                emptyText="Jabatan tidak ditemukan" />
                        </div>

                        <!-- 3. Eselon / RM Band -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                <i class="fas fa-layer-group text-slate-400 text-[10px] mr-1"></i> Eselon / RM Band
                            </label>
                            <x-admin.searchable-select
                                name="rm_level"
                                id="filterRmLevel"
                                :options="$rmLevels"
                                :selected="request('rm_level')"
                                placeholder="Semua Eselon / RM Band"
                                searchPlaceholder="Cari eselon atau level..."
                                icon="fas fa-layer-group"
                                :hasAllOption="true"
                                allOptionText="Semua Eselon / RM Band"
                                emptyText="Eselon tidak ditemukan" />
                        </div>

                        <!-- 4. Tahun Masuk (TMT) -->
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-700 mb-1">
                                <i class="fas fa-calendar-alt text-slate-400 text-[10px] mr-1"></i> Tahun Masuk (TMT)
                            </label>
                            @php
                                $yearOptions = collect($availableYears)->map(function ($year) {
                                    return ['value' => (string) $year, 'label' => 'Tahun ' . $year];
                                })->values()->toArray();
                            @endphp
                            <x-admin.searchable-select
                                name="tahun_masuk"
                                id="filterTahunMasuk"
                                :options="$yearOptions"
                                :selected="request('tahun_masuk')"
                                placeholder="Semua Angkatan"
                                searchPlaceholder="Cari tahun angkatan..."
                                icon="fas fa-calendar-alt"
                                :hasAllOption="true"
                                allOptionText="Semua Angkatan"
                                emptyText="Tahun angkatan tidak ditemukan" />
                        </div>
                    </div>

                    <!-- Panel Action Bar (Apply & Reset) -->
                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2.5 border-t border-slate-200/80">
                        <p class="text-[11px] text-slate-500 flex items-center gap-1.5">
                            <i class="fas fa-info-circle text-emerald-600"></i>
                            <span>Pilih kriteria yang diinginkan, lalu klik <strong>Terapkan Filter</strong>.</span>
                        </p>
                        <div class="flex items-center justify-end gap-2 shrink-0">
                            @if(request()->hasAny(['unit_kerja', 'jabatan', 'rm_level', 'tahun_masuk']))
                                <a href="{{ request()->fullUrlWithQuery(['unit_kerja' => null, 'jabatan' => null, 'rm_level' => null, 'tahun_masuk' => null]) }}"
                                    class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-slate-600 hover:text-danger hover:border-red-200 hover:bg-red-50 text-xs font-semibold transition cursor-pointer shadow-2xs">
                                    Bersihkan Kriteria
                                </a>
                            @endif
                            <button type="submit"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-xs hover:shadow transition active:scale-95 cursor-pointer">
                                <i class="fas fa-check text-[10px]"></i>
                                <span>Terapkan Filter</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Active Filter Tags Display (Excludes Level Tab which is visually active above) -->
                @php
                    $hasAdvancedActiveFilters = request()->hasAny(['search', 'unit_kerja', 'jabatan', 'rm_level', 'tahun_masuk', 'status_khusus']);
                @endphp
                @if ($hasAdvancedActiveFilters)
                    <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-surface-200">
                        <span class="text-xs font-medium text-surface-500">Filter Aktif:</span>
                        
                        @if (request('search'))
                            <a href="{{ request()->fullUrlWithQuery(['search' => null]) }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-primary-50 text-primary-800 border border-primary-200 hover:bg-primary-100 transition">
                                <span>Pencarian: "<strong>{{ request('search') }}</strong>"</span>
                                <i class="fas fa-times text-[10px]"></i>
                            </a>
                        @endif

                        @if (request('unit_kerja'))
                            <a href="{{ request()->fullUrlWithQuery(['unit_kerja' => null]) }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface-100 text-surface-800 border border-surface-300 hover:bg-surface-200 transition">
                                <span>Unit: <strong>{{ request('unit_kerja') }}</strong></span>
                                <i class="fas fa-times text-[10px]"></i>
                            </a>
                        @endif

                        @if (request('jabatan'))
                            <a href="{{ request()->fullUrlWithQuery(['jabatan' => null]) }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-surface-100 text-surface-800 border border-surface-300 hover:bg-surface-200 transition">
                                <span>Jabatan: <strong>{{ request('jabatan') }}</strong></span>
                                <i class="fas fa-times text-[10px]"></i>
                            </a>
                        @endif

                        @if (request('rm_level'))
                            <a href="{{ request()->fullUrlWithQuery(['rm_level' => null]) }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-purple-50 text-purple-800 border border-purple-200 hover:bg-purple-100 transition">
                                <i class="fas fa-layer-group text-[10px] text-purple-600"></i>
                                <span>Eselon: <strong>{{ request('rm_level') }}</strong></span>
                                <i class="fas fa-times text-[10px]"></i>
                            </a>
                        @endif

                        @if (request('tahun_masuk'))
                            <a href="{{ request()->fullUrlWithQuery(['tahun_masuk' => null]) }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100 transition">
                                <i class="fas fa-calendar-alt text-[10px] text-emerald-600"></i>
                                <span>Tahun: <strong>{{ request('tahun_masuk') }}</strong></span>
                                <i class="fas fa-times text-[10px]"></i>
                            </a>
                        @endif

                        @if (request('status_khusus'))
                            @php
                                $statusKhususLabels = [
                                    'rangkap' => 'Tugas Rangkap',
                                    'sanksi' => 'Masa Sanksi (Diperban)',
                                    'overflow' => 'Jabatan Overflow (Advisor)',
                                ];
                                $labelKhusus = $statusKhususLabels[request('status_khusus')] ?? request('status_khusus');
                            @endphp
                            <a href="{{ request()->fullUrlWithQuery(['status_khusus' => null]) }}"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-50 text-red-800 border border-red-200 hover:bg-red-100 transition">
                                <i class="fas fa-shield-alt text-[10px] text-red-600"></i>
                                <span>Tata Kelola: <strong>{{ $labelKhusus }}</strong></span>
                                <i class="fas fa-times text-[10px]"></i>
                            </a>
                        @endif

                        <a href="{{ route('admin.employees.index', array_filter(['level' => request('level')])) }}"
                            class="text-xs text-danger hover:underline ml-1 font-medium">
                            Hapus Filter Tambahan
                        </a>
                    </div>
                @endif
            </form>
        </div>

        <!-- Table Information & Smart Dual Toolbar (Normal vs Bulk Action State) -->
        <div class="px-5 py-3 border-b transition-colors duration-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3"
             :class="selectedNiks.length > 0 ? 'bg-slate-50/90 border-slate-200' : 'bg-white border-surface-200'">
            
            <!-- STATE 1: Normal View (When 0 items selected) -->
            <div x-show="selectedNiks.length === 0" class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full">
                <!-- Left: Table Count Info -->
                <div class="flex items-center gap-2.5">
                    <span class="text-sm font-bold text-surface-900">Daftar Karyawan</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-surface-100 text-surface-700">
                        {{ $employees->total() }} Pegawai
                    </span>
                    @if ($employees->isNotEmpty())
                        <span class="text-xs text-surface-400 hidden md:inline">|</span>
                        <span class="text-xs text-surface-500 tabular-nums hidden md:inline">
                            Menampilkan <strong class="text-surface-800">{{ $employees->firstItem() }}</strong> – <strong class="text-surface-800">{{ $employees->lastItem() }}</strong> dari <strong class="text-surface-800">{{ $employees->total() }}</strong> data
                        </span>
                    @endif
                </div>

                <!-- Right: Action Buttons in Table Toolbar (Smart Export) -->
                <div class="flex items-center gap-2">
                    <form action="{{ route('admin.employees.export') }}" method="GET" id="tableExportForm" class="inline">
                        @foreach(request()->query() as $key => $value)
                            @if($value !== null && $value !== '')
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach

                        <button type="submit" id="tableExportBtn"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 text-xs font-semibold transition shadow-xs group cursor-pointer"
                            title="{{ request()->hasAny(['search', 'unit_kerja', 'jabatan', 'level', 'golongan', 'tahun_masuk', 'bulan_masuk']) ? 'Export data pegawai sesuai filter aktif' : 'Export seluruh data pegawai ke Excel' }}">
                            <i class="fas fa-file-excel text-emerald-600 text-xs group-hover:scale-110 transition-transform"></i>
                            <span id="tableExportBtnText">Export Data</span>
                            <span class="px-1.5 py-0.2 rounded-md bg-slate-100 text-slate-600 font-mono text-[10px]">
                                {{ $employees->total() }}
                            </span>
                        </button>
                    </form>
                </div>
            </div>

            <!-- STATE 2: Slim Neutral Contextual Strip (When >= 1 items selected) -->
            <div x-show="selectedNiks.length > 0" x-cloak class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 w-full animate-fade-in">
                <!-- Left: Selected Count, Selection Utilities & Keyboard Hint -->
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-primary-100 text-primary-800 font-bold text-xs shadow-2xs">
                        <i class="fas fa-check text-[10px] text-primary-700"></i>
                        <span><strong x-text="selectedNiks.length"></strong> Terpilih</span>
                    </span>
                    <span class="text-xs text-slate-300">•</span>
                    <button type="button" @click="toggleSelectAll(true)"
                        class="text-xs font-semibold text-slate-600 hover:text-primary-700 transition cursor-pointer">
                        Pilih Semua ({{ $employees->count() }})
                    </button>
                    <span class="text-xs text-slate-300">•</span>
                    <button type="button" @click="deselectAll()"
                        class="text-xs font-medium text-slate-500 hover:text-slate-800 transition cursor-pointer">
                        Batalkan Pilihan
                    </button>
                    <span class="text-xs text-slate-300 hidden xl:inline">•</span>
                    <span class="text-[11px] text-slate-400 hidden xl:inline items-center gap-1">
                        <kbd class="px-1.5 py-0.5 rounded bg-white border border-slate-200 text-slate-500 font-mono text-[10px] shadow-2xs">Shift</kbd> + klik baris untuk rentang
                    </span>
                </div>

                <!-- Right: Quick Export & Delete Trigger -->
                <div class="flex items-center gap-2">
                    <!-- Export Selected -->
                    <form action="{{ route('admin.employees.export') }}" method="GET" class="inline">
                        <template x-for="nik in selectedNiks" :key="'top-export-' + nik">
                            <input type="hidden" name="niks[]" :value="nik">
                        </template>
                        <button type="submit"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-primary-800 text-xs font-semibold transition shadow-2xs cursor-pointer">
                            <i class="fas fa-file-excel text-emerald-600 text-xs"></i>
                            <span>Export (<span x-text="selectedNiks.length"></span>)</span>
                        </button>
                    </form>

                    <!-- Bulk Delete Trigger (Clean Subdued Outline) -->
                    <button type="button" @click="bulkDeleteModalOpen = true"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-red-200 bg-white text-danger hover:bg-red-50 text-xs font-semibold transition shadow-2xs cursor-pointer">
                        <i class="fas fa-trash-alt text-xs"></i>
                        <span>Hapus</span>
                    </button>
                </div>
            </div>

        </div>

        <!-- Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-10 bg-surface-50 border-b border-surface-200 shadow-2xs">
                    <tr class="text-xs font-semibold text-surface-700 uppercase tracking-wider">
                        <!-- Dedicated Clean Checkbox Column -->
                        <th scope="col" class="py-3.5 pl-5 pr-2 w-10 text-center">
                            <div class="w-4 h-4 rounded border flex items-center justify-center cursor-pointer transition mx-auto shrink-0"
                                 :class="isAllSelected ? 'bg-primary-600 border-primary-600 text-white shadow-2xs' : (selectedNiks.length > 0 ? 'bg-primary-100 border-primary-500 text-primary-700' : 'bg-white border-slate-300 hover:border-primary-500 shadow-2xs')"
                                 @click.stop="toggleSelectAll(!isAllSelected)"
                                 title="Pilih Semua Pegawai di Halaman Ini (Tips: Tahan Ctrl+Klik atau Shift+Klik pada baris untuk rentang)">
                                <template x-if="isAllSelected">
                                    <i class="fas fa-check text-[10px] text-white"></i>
                                </template>
                                <template x-if="!isAllSelected && selectedNiks.length > 0">
                                    <i class="fas fa-minus text-[8px] text-primary-700"></i>
                                </template>
                            </div>
                        </th>
                        <x-admin.table.sort-th column="nama" label="Pegawai" class="min-w-[200px]" />
                        <x-admin.table.sort-th column="jabatan" label="Jabatan & Unit Kerja" class="min-w-[260px]" />
                        <x-admin.table.sort-th column="level" label="Strata" align="center" class="w-28 whitespace-nowrap" />
                        <x-admin.table.sort-th column="rm_level" label="Eselon" align="center" class="w-28 whitespace-nowrap" />
                        <x-admin.table.sort-th column="golongan" label="Golongan" align="center" class="w-28 whitespace-nowrap" />
                        <th scope="col" class="py-3.5 px-5 text-right w-16 whitespace-nowrap">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-sm">
                    @forelse ($employees as $employee)
                        <x-admin.employee-table-row :employee="$employee" />
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-16 text-center">
                                <div class="max-w-sm mx-auto flex flex-col items-center">
                                    <div class="w-16 h-16 rounded-full bg-surface-100 text-surface-400 flex items-center justify-center text-2xl mb-4 border border-surface-200">
                                        <i class="fas fa-users-slash"></i>
                                    </div>
                                    <h3 class="text-base font-semibold text-surface-900 mb-1">Tidak ada data karyawan ditemukan</h3>
                                    @php
                                        $hasActiveFilters = request()->hasAny([
                                            'search', 'unit_kerja', 'jabatan', 'level', 'rm_level',
                                            'golongan', 'tahun_masuk', 'bulan_masuk', 'status_penugasan'
                                        ]);
                                    @endphp
                                    <p class="text-xs text-surface-500 mb-4 leading-relaxed">
                                        @if($hasActiveFilters)
                                            Tidak ditemukan data yang sesuai dengan kriteria 
                                            @if(request('search'))
                                                pencarian "<strong>{{ request('search') }}</strong>"
                                            @endif
                                            @if(request()->hasAny(['unit_kerja', 'jabatan', 'rm_level', 'tahun_masuk']))
                                                {{ request('search') ? 'dan' : '' }} filter aktif Anda.
                                            @else
                                                Anda.
                                            @endif
                                            Coba sesuaikan kata kunci atau reset filter untuk menampilkan data lainnya.
                                        @else
                                            Belum ada data karyawan terdaftar di sistem. Anda dapat menambah karyawan baru atau mengimpor dari file Excel.
                                        @endif
                                    </p>
                                    @if($hasActiveFilters)
                                        <a href="{{ route('admin.employees.index') }}"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold border border-slate-300 transition shadow-2xs">
                                            <i class="fas fa-rotate-left text-xs"></i>
                                            <span>Reset Pencarian &amp; Filter</span>
                                        </a>
                                    @else
                                        <a href="{{ route('admin.employees.create') }}"
                                            class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold shadow-sm transition">
                                            <i class="fas fa-plus text-xs"></i>
                                            <span>Tambah Karyawan Pertama</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="p-4 border-t border-surface-200 bg-surface-50">
            <x-pagination-ui :data="$employees" />
        </div>
    </div>

    <!-- MODAL: Import Excel & Template (Comprehensive Enterprise Import Dialog) -->
    <div x-show="importModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="modal-title" role="dialog" aria-modal="true">
        
        <!-- Backdrop -->
        <div x-show="importModalOpen"
            x-transition:enter="ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-neutral-900/60 backdrop-blur-xs transition-opacity"
            @click="importModalOpen = false"></div>

        <div class="flex min-h-full items-center justify-center p-3 sm:p-4 text-center">
            <div x-show="importModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-2xl transition-all my-auto w-full sm:max-w-3xl border border-surface-200 flex flex-col"
                style="max-width: 48rem; max-height: calc(100vh - 3.5rem);">
                
                <!-- Modal Header -->
                <div class="px-6 py-3.5 sm:py-4 border-b border-surface-200 flex items-center justify-between bg-surface-50/90 shrink-0">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-lg border border-primary-200 shadow-2xs shrink-0">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-surface-900" id="modal-title">Import Data Pegawai</h3>
                            <p class="text-xs text-surface-500 mt-0.5">Unggah spreadsheet Excel (.xlsx, .xls) untuk penambahan atau pembaruan massal.</p>
                        </div>
                    </div>
                    <button type="button" @click="importModalOpen = false"
                        class="w-8 h-8 rounded-lg text-surface-400 hover:text-surface-700 hover:bg-surface-200/60 transition flex items-center justify-center cursor-pointer shrink-0"
                        aria-label="Tutup modal">
                        <i class="fas fa-times text-xs"></i>
                    </button>
                </div>

                <!-- Navigation Tabs (Modern Segmented Pill Tabs - Linear/Stripe Standard) -->
                <div class="px-6 py-2.5 bg-surface-50/70 border-b border-surface-200/80 flex items-center shrink-0">
                    <div class="inline-flex p-1 bg-surface-200/60 rounded-xl gap-1">
                        <button type="button" @click="importModalTab = 'upload'"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap"
                            :class="importModalTab === 'upload' ? 'bg-white text-surface-900 shadow-xs font-bold' : 'text-surface-600 hover:text-surface-900 hover:bg-white/40'">
                            <i class="fas fa-cloud-arrow-up text-[11px]" :class="importModalTab === 'upload' ? 'text-primary-600' : 'text-surface-400'"></i>
                            <span>Unggah Berkas</span>
                        </button>
                        <button type="button" @click="importModalTab = 'specs'"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 text-xs font-semibold rounded-lg transition-all cursor-pointer whitespace-nowrap"
                            :class="importModalTab === 'specs' ? 'bg-white text-surface-900 shadow-xs font-bold' : 'text-surface-600 hover:text-surface-900 hover:bg-white/40'">
                            <i class="fas fa-table-list text-[11px]" :class="importModalTab === 'specs' ? 'text-primary-600' : 'text-surface-400'"></i>
                            <span>Panduan Format Kolom</span>
                        </button>
                    </div>
                </div>

                <!-- TAB 1: UNGGAH BERKAS -->
                <div x-show="importModalTab === 'upload'" class="px-6 py-4 space-y-3.5 flex-1 min-h-0 overflow-y-auto">
                    <!-- Step 1: Download Template Area -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                <label class="text-xs font-bold text-surface-900">Format Template Resmi PTPN IV</label>
                            </div>
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-100 text-[10px] font-semibold text-emerald-800 border border-emerald-200">
                                <i class="fas fa-check-circle text-[9px]"></i> 2 Sheet & Dropdown Validasi
                            </span>
                        </div>

                        <div class="bg-primary-50/60 border border-primary-200/90 rounded-2xl p-4 sm:p-4.5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shadow-2xs">
                            <div class="space-y-1 flex-1">
                                <h5 class="text-xs font-bold text-primary-950 flex items-center gap-1.5">
                                    <i class="fas fa-file-circle-check text-emerald-600 text-xs"></i>
                                    <span>Format Spreadsheet Terstandarisasi</span>
                                </h5>
                                <p class="text-xs text-surface-600 leading-relaxed">
                                    Dilengkapi pilihan <strong>dropdown otomatis</strong> dan <strong>lembar referensi</strong> untuk memastikan akurasi data serta mencegah kesalahan pengetikan.
                                </p>
                            </div>
                            <a href="{{ route('admin.employees.downloadTemplate') }}"
                                class="h-9 shrink-0 inline-flex items-center gap-2 px-4 rounded-xl bg-white border border-primary-300 text-primary-800 text-xs font-semibold hover:bg-primary-50 hover:border-primary-400 transition-all shadow-2xs group whitespace-nowrap active:scale-[0.98] cursor-pointer">
                                <i class="fas fa-file-excel text-emerald-600 group-hover:scale-110 transition-transform"></i>
                                <span>Unduh Template Excel</span>
                            </a>
                        </div>
                    </div>

                    <!-- Step 2: Upload File Area -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                                <label class="text-xs font-bold text-surface-900">Pilih Berkas Excel untuk Diunggah</label>
                            </div>
                            <button type="button" @click="importModalTab = 'specs'"
                                class="text-xs text-primary-700 hover:text-primary-800 font-semibold hover:underline inline-flex items-center gap-1 cursor-pointer">
                                <i class="fas fa-circle-question text-[11px]"></i>
                                <span>Lihat Spesifikasi 25 Kolom</span>
                            </button>
                        </div>

                        <form action="{{ route('admin.employees.import') }}" method="POST" enctype="multipart/form-data" id="modalImportForm">
                            @csrf
                            <div id="modalDropZone"
                                class="relative border-2 border-dashed border-surface-300 hover:border-primary-500 rounded-2xl p-4 sm:p-5 text-center cursor-pointer transition-all duration-200 bg-surface-50/60 hover:bg-primary-50/30 group">
                                <input type="file" name="import_file" id="modal_import_file"
                                    accept=".xls,.xlsx" required
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    onchange="handleModalFileChange(this)">

                                <!-- Default Dropzone State -->
                                <div id="modalFileDefault" class="py-1">
                                    <div class="w-11 h-11 sm:w-12 sm:h-12 rounded-xl bg-surface-100 text-surface-500 group-hover:bg-primary-100 group-hover:text-primary-700 group-hover:scale-105 flex items-center justify-center mx-auto text-lg sm:text-xl transition duration-200 shadow-2xs border border-surface-200 group-hover:border-primary-200">
                                        <i class="fas fa-cloud-arrow-up"></i>
                                    </div>
                                    <p class="text-sm font-bold text-surface-800 mt-2.5 group-hover:text-primary-900 transition">Klik untuk memilih berkas atau seret ke sini</p>
                                    <p class="text-xs text-surface-400 mt-1">Mendukung format Excel <span class="font-semibold text-surface-600">.xlsx, .xls</span> &bull; Ukuran maksimal <span class="font-semibold text-surface-600">10 MB</span></p>
                                </div>

                                <!-- Enterprise Selected File Card -->
                                <div id="modalFileSelected" class="hidden">
                                    <div class="p-3.5 bg-emerald-50/80 border border-emerald-200 rounded-xl flex items-center justify-between gap-3 text-left shadow-2xs">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg shrink-0 border border-emerald-200 shadow-2xs">
                                                <i class="fas fa-file-excel"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <p class="text-xs font-bold text-surface-900 truncate max-w-xs" id="modalFileName"></p>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold border border-emerald-200 shrink-0">
                                                        <i class="fas fa-check text-[9px]"></i> Siap Diimpor
                                                    </span>
                                                </div>
                                                <p class="text-[11px] text-surface-500 mt-0.5 flex items-center gap-2">
                                                    <span id="modalFileSize" class="font-mono font-medium text-surface-700"></span>
                                                    <span>&bull;</span>
                                                    <span class="text-emerald-700 font-medium">Format tervalidasi</span>
                                                </p>
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearModalFile(event)" class="shrink-0 px-3 py-1.5 text-xs text-danger hover:text-red-700 hover:bg-red-50 rounded-lg transition font-medium border border-transparent hover:border-red-200 cursor-pointer">
                                            <i class="fas fa-trash-can mr-1"></i> Ganti Berkas
                                        </button>
                                    </div>
                                </div>

                                <!-- Error State -->
                                <div id="modalFileError" class="hidden">
                                    <div class="p-3.5 bg-red-50/80 border border-red-200 rounded-xl flex items-center justify-between gap-3 text-left shadow-2xs">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="w-10 h-10 rounded-xl bg-red-100 text-danger flex items-center justify-center text-lg shrink-0 border border-red-200">
                                                <i class="fas fa-triangle-exclamation"></i>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-xs font-bold text-danger truncate" id="modalFileErrorMsg"></p>
                                                <p class="text-[11px] text-surface-500 mt-0.5">Pilih berkas berekstensi .xlsx atau .xls dengan ukuran maksimal 10MB.</p>
                                            </div>
                                        </div>
                                        <button type="button" onclick="clearModalFile(event)" class="shrink-0 px-3 py-1.5 text-xs text-primary-700 hover:text-primary-800 hover:bg-primary-50 rounded-lg transition font-medium border border-transparent hover:border-primary-200 cursor-pointer">
                                            Pilih Berkas Lain
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- TAB 2: PANDUAN SPESIFIKASI 25 KOLOM -->
                <div x-show="importModalTab === 'specs'" class="flex-1 min-h-0 flex flex-col">
                    <!-- Pinned Subheader & Filter Bar (Tetap di atas, tidak tergulung, tidak tertimpa baris tabel) -->
                    <div class="px-6 py-3.5 border-b border-surface-200 bg-surface-50/70 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 shrink-0">
                        <div>
                            <h4 class="text-xs font-bold text-surface-900">Kamus Kolom Impor Data Pegawai</h4>
                            <p class="text-[11px] text-surface-500 mt-0.5">Panduan penamaan atribut kolom dan aturan format pengisian spreadsheet.</p>
                        </div>
                        <div class="inline-flex items-center gap-1 p-1 bg-surface-100 rounded-xl border border-surface-200/70 shadow-2xs text-[11px] font-semibold shrink-0">
                            <button type="button" @click="specsFilter = 'all'"
                                class="h-7 px-3 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                :class="specsFilter === 'all' ? 'bg-white text-surface-900 shadow-2xs font-bold' : 'text-surface-500 hover:text-surface-800'">
                                Semua (25)
                            </button>
                            <button type="button" @click="specsFilter = 'mandatory'"
                                class="h-7 px-3 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                :class="specsFilter === 'mandatory' ? 'bg-white text-danger shadow-2xs font-bold' : 'text-surface-500 hover:text-danger'">
                                Wajib (6)
                            </button>
                            <button type="button" @click="specsFilter = 'optional'"
                                class="h-7 px-3 rounded-lg transition-all cursor-pointer whitespace-nowrap"
                                :class="specsFilter === 'optional' ? 'bg-white text-surface-900 shadow-2xs font-bold' : 'text-surface-500 hover:text-surface-800'">
                                Opsional (19)
                            </button>
                        </div>
                    </div>

                    <!-- Scrolling Table Area (Hanya area tabel ini yang bergulir) -->
                    <div class="flex-1 min-h-0 overflow-y-auto px-6 py-4">
                        <!-- Spesifikasi Kolom Table -->
                        <div class="border border-surface-200 rounded-xl overflow-hidden shadow-2xs overflow-x-auto">
                            <table class="w-full text-left border-collapse text-xs min-w-[540px] sm:min-w-full">
                                <thead class="sticky top-0 bg-surface-100 border-b border-surface-200 text-surface-700 text-[11px] uppercase tracking-wider font-semibold z-10 shadow-2xs">
                                    <tr>
                                        <th class="py-2.5 px-3.5 font-bold w-[24%] bg-surface-100">Kolom & Status</th>
                                        <th class="py-2.5 px-3.5 font-bold w-[26%] bg-surface-100">Tipe & Dropdown</th>
                                        <th class="py-2.5 px-3.5 font-bold w-[50%] bg-surface-100">Aturan & Contoh Pengisian</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-surface-100 text-[11px] bg-white">
                                <!-- 6 MANDATORY (CLEAN TYPOGRAPHY - NO BADGE OVERLOAD) -->
                                <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                            <span>nik</span>
                                            <span class="text-danger" title="Wajib Diisi">*</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Teks (8 Digit)</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Format Teks Baku</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        8 digit angka numerik unik. Auto-padding nol di depan jika terpotong Excel. Contoh: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">13009988</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                            <span>nama</span>
                                            <span class="text-danger" title="Wajib Diisi">*</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Teks Bebas</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Nama Lengkap KTP</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Nama lengkap pegawai sesuai KTP. Otomatis distandarkan menjadi UPPERCASE oleh sistem.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                            <span>status_karyawan</span>
                                            <span class="text-danger" title="Wajib Diisi">*</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Pilihan Validasi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Pilih dari dropdown: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Tetap (PKWTT)</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Kontrak (PKWT)</code>, atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Calon Pegawai</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                            <span>jabatan</span>
                                            <span class="text-danger" title="Wajib Diisi">*</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Master Jabatan</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Harus sesuai dengan formasi jabatan aktif pada Unit Kerja & Strata yang dipilih.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                            <span>level</span>
                                            <span class="text-danger" title="Wajib Diisi">*</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Strata Pegawai</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Strata kepegawaian: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Karpim</code> (Pimpinan) atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Karpel</code> (Pelaksana)
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'mandatory'" class="hover:bg-red-50/20 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="flex items-center gap-1 font-mono font-bold text-surface-900">
                                            <span>unit_kerja</span>
                                            <span class="text-danger" title="Wajib Diisi">*</span>
                                        </div>
                                        <span class="text-[10px] font-semibold text-danger block mt-0.5">• Wajib Diisi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">43 Master Unit</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Pilih salah satu dari 43 Unit Kerja resmi PTPN IV Regional V (lihat Sheet 2 template).
                                    </td>
                                </tr>

                                <!-- 19 OPTIONAL (CLEAN ENTERPRISE TYPOGRAPHY) -->
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">jenis_kelamin</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Pilihan Nilai</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Pilihan: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">L</code> (Laki-laki) atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">P</code> (Perempuan)
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tempat_lahir</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Teks Bebas</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Kota / kabupaten kelahiran sesuai KTP.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tanggal_lahir</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Format YYYY-MM-DD. Menjadi dasar kalkulasi otomatis Pensiun (56 thn) & MBT (55 thn).
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">agama</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">6 Agama Resmi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        6 Agama resmi: Islam, Kristen Protestan, Katolik, Hindu, Buddha, Khonghucu.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">susunan_keluarga</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Status PTKP</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Status PTKP Pajak: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">L</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">TK</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">K/0</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">K/1</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">K/2</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">K/3</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">golongan</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Format Baku</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">[Gol]/[Berkala]</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Format baku: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">[Gol]/[Berkala]</code>. Contoh: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">IIIA/00</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">IIIB/02</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">IVA/05</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">rm_level</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Eselon RM</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Eselon Formasi: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">RM-1</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">RM-2</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">RM-3</code>, atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">RM-4</code>. Dihitung otomatis jika kosong.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">status_penugasan</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Status Tugas</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Pilihan: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Definitif</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Diperbantukan</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Pejabat Sementara (Pjs)</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Advisor</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tmt_bekerja</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        TMT pengangkatan pertama di PTPN (minimal 18 tahun setelah tanggal lahir).
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tanggal_diangkat_staf</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Tanggal resmi diangkat Karpim (kosongkan jika pegawai adalah Karpel).
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tanggal_dalam_jabatan</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Tanggal SK posisi jabatan saat ini. Minimal sama dengan TMT Bekerja.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tmt_unit_kerja</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Tanggal mulai bertugas di unit kerja saat ini (dasar rotasi dinas).
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">jalur_masuk</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Pipa Masuk</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Pipa MPP: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">CKP</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Talent Scouting</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">RBB</code>, atau <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">Reguler</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">job_grade</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Angka Bulat</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Grade Jabatan</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Karpel: rentang 1 s.d. 10. Karpim: rentang 11 s.d. 16.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">person_grade</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Angka Bulat</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Grade Personal</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Karpel: rentang 1 s.d. 10. Karpim: rentang 11 s.d. 16.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">pendidikan_terakhir</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="text-[11px] font-semibold text-emerald-700 inline-flex items-center gap-1 whitespace-nowrap">
                                            <i class="fas fa-circle-chevron-down text-[10px]"></i> In-Cell Dropdown
                                        </div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">Jenjang Studi</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Jenjang: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">SD</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">SMP</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">SMA / SMK</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">D3</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">D4 / S1</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">S2</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">S3</code>
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">sekolah</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Teks Bebas</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Nama universitas / institut / sekolah asal. Contoh: <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">INSTIPER</code>, <code class="px-1.5 py-0.5 rounded bg-surface-100 text-surface-800 font-mono text-[11px] border border-surface-200">UGM</code>.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tanggal_mbt</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Masa Bebas Tugas (55 thn). Kosongkan agar dihitung otomatis oleh sistem dari tanggal lahir.
                                    </td>
                                </tr>
                                <tr x-show="specsFilter === 'all' || specsFilter === 'optional'" class="hover:bg-surface-50 transition">
                                    <td class="py-2.5 px-3.5 align-top">
                                        <div class="font-mono font-medium text-surface-800">tanggal_pensiun</div>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">• Opsional</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top">
                                        <span class="text-[11px] text-surface-700 font-medium">Tanggal</span>
                                        <span class="text-[10px] text-surface-400 block mt-0.5">YYYY-MM-DD</span>
                                    </td>
                                    <td class="py-2.5 px-3.5 align-top text-surface-600 leading-relaxed text-[11px]">
                                        Batas Usia Pensiun (56 thn). Kosongkan agar dihitung otomatis oleh sistem dari tanggal lahir.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

                <!-- Modal Footer (Enterprise Crisp Button System) -->
                <div class="px-6 py-3.5 bg-surface-50 border-t border-surface-200 flex items-center justify-between gap-3 shrink-0">
                    <div class="min-w-0 flex-1">
                        <span x-show="importModalTab === 'specs'" class="hidden sm:inline-flex items-center gap-1.5 text-[11px] text-surface-500 font-medium truncate">
                            <i class="fas fa-circle-check text-emerald-600 text-[10px]"></i>
                            <span>Format tervalidasi sistem</span>
                        </span>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" @click="importModalOpen = false"
                            class="h-9 px-4 rounded-lg text-surface-600 hover:text-surface-900 hover:bg-surface-200/60 text-xs font-semibold transition-all cursor-pointer whitespace-nowrap shrink-0">
                            Tutup
                        </button>
                        <button x-show="importModalTab === 'specs'" type="button" @click="importModalTab = 'upload'"
                            class="h-9 inline-flex items-center gap-2 px-4.5 rounded-lg bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-xs font-semibold transition-all shadow-xs active:scale-[0.98] cursor-pointer whitespace-nowrap shrink-0">
                            <span>Lanjut Unggah Berkas</span>
                            <i class="fas fa-arrow-right text-xs"></i>
                        </button>
                        <button x-show="importModalTab === 'upload'" type="submit" form="modalImportForm" id="modalImportSubmitBtn"
                            class="h-9 inline-flex items-center gap-2 px-5 rounded-lg bg-primary-600 hover:bg-primary-700 active:bg-primary-800 text-white text-xs font-semibold transition-all shadow-xs active:scale-[0.98] cursor-pointer whitespace-nowrap shrink-0">
                            <i class="fas fa-upload text-xs" id="modalImportIcon"></i>
                            <span id="modalImportBtnText">Mulai Import Data</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: Delete Confirmation Modal (Destructive Action - DESIGN.md 6.5) -->
    <div x-show="deleteModalOpen"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="delete-dialog-title" role="dialog" aria-modal="true">
        
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
                        <div class="w-12 h-12 rounded-2xl bg-red-100 text-danger flex items-center justify-center text-xl shrink-0 border border-red-200">
                            <i class="fas fa-triangle-exclamation"></i>
                        </div>
                        <div class="space-y-1.5 flex-1">
                            <h3 class="text-lg font-bold text-surface-900" id="delete-dialog-title">Hapus Data Karyawan</h3>
                            <p class="text-xs sm:text-sm text-surface-600 leading-relaxed">
                                Apakah Anda yakin ingin menghapus data pegawai <strong class="text-surface-900 font-semibold" x-text="deleteNama"></strong> (NIK: <span class="font-mono text-surface-700 font-semibold" x-text="deleteNik"></span>)?
                            </p>
                            <p class="text-xs text-danger font-medium mt-2">
                                <i class="fas fa-circle-exclamation mr-1"></i> Data riwayat mutasi, pelatihan, dan evaluasi terkait akan ikut terhapus permanen. Tindakan ini tidak dapat dibatalkan.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2.5">
                    <button type="button" @click="deleteModalOpen = false"
                        class="px-4 py-2.5 rounded-lg border border-surface-300 bg-white text-surface-700 hover:bg-surface-100 text-xs sm:text-sm font-medium transition shadow-xs">
                        Batal
                    </button>
                    <form :action="deleteAction" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-danger hover:bg-red-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm">
                            <i class="fas fa-trash-alt text-xs"></i>
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
        class="fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-slate-900/95 backdrop-blur-md text-white px-4 py-2.5 rounded-2xl shadow-2xl flex items-center gap-3.5 border border-slate-700/80 max-w-[95vw]">
        
        <!-- Counter Badge & Deselect -->
        <div class="flex items-center gap-2.5 pr-2 border-r border-slate-700/80">
            <span class="w-6 h-6 rounded-full bg-emerald-500 text-slate-950 font-bold font-mono text-xs flex items-center justify-center shrink-0 shadow-xs"
                x-text="selectedNiks.length"></span>
            <span class="text-xs font-semibold text-slate-200 whitespace-nowrap"><span x-text="selectedNiks.length"></span> Dipilih</span>
            <button type="button" @click="deselectAll()"
                class="text-xs text-slate-400 hover:text-white transition px-1.5 py-0.5 rounded-md hover:bg-slate-800 cursor-pointer flex items-center gap-1"
                title="Batalkan Pilihan (Esc)">
                <i class="fas fa-times text-[11px]"></i>
                <kbd class="text-[9px] bg-slate-800 text-slate-400 px-1 py-0.2 rounded font-mono border border-slate-700 hidden sm:inline">Esc</kbd>
            </button>
        </div>

        <!-- Action: Export Selected to Excel -->
        <form action="{{ route('admin.employees.export') }}" method="GET" class="inline">
            <template x-for="nik in selectedNiks" :key="'export-' + nik">
                <input type="hidden" name="niks[]" :value="nik">
            </template>
            <button type="submit"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-xs font-semibold shadow-xs transition active:scale-95 cursor-pointer">
                <i class="fas fa-file-excel text-xs"></i>
                <span>Export Excel</span>
            </button>
        </form>

        <!-- Action: Bulk Delete Modal Trigger (Protected Dangerous State) -->
        <button type="button" @click="bulkDeleteModalOpen = true"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-red-900/40 text-red-400 hover:text-red-300 border border-slate-700 hover:border-red-800/60 text-xs font-semibold transition active:scale-95 cursor-pointer">
            <i class="fas fa-trash-alt text-xs"></i>
            <span>Hapus</span>
        </button>
    </div>

    <!-- Bulk Delete Confirmation Modal (Enterprise Destructive Safety) -->
    <div x-show="bulkDeleteModalOpen" x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        aria-labelledby="bulk-delete-dialog-title" role="dialog" aria-modal="true">
        
        <div x-show="bulkDeleteModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 bg-slate-900/60 backdrop-blur-xs transition-opacity"
            @click="bulkDeleteModalOpen = false"></div>

        <div class="flex min-h-screen items-center justify-center p-4">
            <div x-show="bulkDeleteModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95"
                x-transition:enter-end="opacity-100 scale-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100"
                x-transition:leave-end="opacity-0 scale-95"
                class="relative bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 overflow-hidden text-left"
                @click.stop>
                
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-50 text-danger border border-red-200 flex items-center justify-center text-xl shrink-0">
                        <i class="fas fa-triangle-exclamation"></i>
                    </div>
                    <div class="flex-1 space-y-2">
                        <h3 class="text-lg font-bold text-slate-900" id="bulk-delete-dialog-title">
                            Hapus Massal Pegawai Terpilih
                        </h3>
                        <p class="text-xs sm:text-sm text-slate-600 leading-relaxed">
                            Anda akan menghapus <strong class="text-slate-900 font-semibold" x-text="selectedNiks.length"></strong> data pegawai yang dicentang. Seluruh berkas foto profil, data riwayat penempatan, evaluasi, dan pelatihan yang melekat akan ikut terhapus secara permanen.
                        </p>
                        
                        <!-- Selected NIK list preview -->
                        <div class="mt-3 p-2.5 bg-slate-50 rounded-xl border border-slate-200 max-h-28 overflow-y-auto">
                            <span class="text-[11px] font-semibold text-slate-500 block mb-1">NIK Terpilih:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="nik in selectedNiks" :key="'badge-' + nik">
                                    <span class="px-2 py-0.5 rounded-md bg-white border border-slate-200 font-mono text-[11px] text-slate-700 shadow-2xs"
                                        x-text="nik"></span>
                                </template>
                            </div>
                        </div>

                        <p class="text-xs text-danger font-medium mt-2">
                            <i class="fas fa-circle-exclamation mr-1"></i> Tindakan penghapusan massal ini tidak dapat dibatalkan (Irreversible).
                        </p>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-100 flex items-center justify-end gap-2.5">
                    <button type="button" @click="bulkDeleteModalOpen = false"
                        class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-semibold transition cursor-pointer shadow-2xs">
                        Batal
                    </button>
                    <form action="{{ route('admin.employees.bulkDestroy') }}" method="POST" class="inline">
                        @csrf
                        <template x-for="nik in selectedNiks" :key="'delete-' + nik">
                            <input type="hidden" name="niks[]" :value="nik">
                        </template>
                        <button type="submit"
                            class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-danger hover:bg-red-700 text-white text-xs font-semibold transition shadow-xs cursor-pointer">
                            <i class="fas fa-trash-alt text-xs"></i>
                            <span>Ya, Hapus <span x-text="selectedNiks.length"></span> Pegawai</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Enhanced JS for File Upload, Drag & Drop & Table Export -->
<script>
    function handleModalFileChange(input) {
        const fileDefault = document.getElementById('modalFileDefault');
        const fileSelected = document.getElementById('modalFileSelected');
        const fileError = document.getElementById('modalFileError');
        const fileName = document.getElementById('modalFileName');
        const fileSize = document.getElementById('modalFileSize');
        const errorMsg = document.getElementById('modalFileErrorMsg');
        const submitBtn = document.getElementById('modalImportSubmitBtn');

        fileDefault?.classList.add('hidden');
        fileSelected?.classList.add('hidden');
        fileError?.classList.add('hidden');

        if (input.files && input.files[0]) {
            const file = input.files[0];
            const validExtensions = ['xlsx', 'xls'];
            const ext = file.name.split('.').pop().toLowerCase();
            const maxSize = 10 * 1024 * 1024; // 10MB

            if (!validExtensions.includes(ext)) {
                if (errorMsg) errorMsg.textContent = 'Format berkas tidak didukung: .' + ext;
                fileError?.classList.remove('hidden');
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            if (file.size > maxSize) {
                if (errorMsg) errorMsg.textContent = 'Ukuran berkas (' + (file.size / (1024 * 1024)).toFixed(1) + ' MB) melebihi batas 10MB';
                fileError?.classList.remove('hidden');
                if (submitBtn) submitBtn.disabled = true;
                return;
            }

            if (fileName) fileName.textContent = file.name;
            if (fileSize) {
                fileSize.textContent = file.size < 1024 * 1024
                    ? (file.size / 1024).toFixed(1) + ' KB'
                    : (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            }
            fileSelected?.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = false;
        } else {
            fileDefault?.classList.remove('hidden');
            if (submitBtn) submitBtn.disabled = false;
        }
    }

    function clearModalFile(e) {
        e.preventDefault();
        e.stopPropagation();
        const fileInput = document.getElementById('modal_import_file');
        if (fileInput) fileInput.value = '';
        handleModalFileChange(fileInput);
    }

    // Modal Import Submit State
    document.getElementById('modalImportForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('modalImportSubmitBtn');
        const btnText = document.getElementById('modalImportBtnText');
        const icon = document.getElementById('modalImportIcon');

        if (btn && btnText) {
            btn.disabled = true;
            if (icon) icon.className = 'fas fa-spinner fa-spin text-xs';
            btnText.textContent = 'Memproses Data...';
        }
    });

    // Table Smart Export Loading State
    document.getElementById('tableExportForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('tableExportBtn');
        const btnText = document.getElementById('tableExportBtnText');

        btn.disabled = true;
        btnText.textContent = 'Mengunduh...';

        setTimeout(function() {
            btn.disabled = false;
            btnText.textContent = 'Export Excel ({{ $employees->total() }})';
        }, 3000);
    });

    // Drag and Drop enhancement for Modal
    const dropZone = document.getElementById('modalDropZone');
    if (dropZone) {
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function(e) {
                e.preventDefault();
                e.stopPropagation();
            }, false);
        });

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.add('border-primary-500', 'bg-primary-50/50');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, function() {
                dropZone.classList.remove('border-primary-500', 'bg-primary-50/50');
            }, false);
        });

        dropZone.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            if (files.length > 0) {
                const fileInput = document.getElementById('modal_import_file');
                fileInput.files = files;
                handleModalFileChange(fileInput);
            }
        }, false);
    }
</script>

<x-toast />
@endsection
