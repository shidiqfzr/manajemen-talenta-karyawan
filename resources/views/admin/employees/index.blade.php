@extends('layouts.admin')

@section('content')
<div x-data="{
    deleteModalOpen: false,
    bulkDeleteModalOpen: false,
    showAdvancedFilters: {{ request()->hasAny(['unit_kerja', 'jabatan', 'rm_level', 'tahun_masuk']) ? 'true' : 'false' }},
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
            <button type="button" @click="$dispatch('open-import-modal')"
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

                        <button type="button" @click="triggerExport('tableExportForm')" :disabled="isExporting" id="tableExportBtn"
                            class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-slate-900 text-xs font-semibold transition shadow-xs group cursor-pointer disabled:opacity-60 disabled:cursor-wait"
                            title="{{ request()->hasAny(['search', 'unit_kerja', 'jabatan', 'level', 'golongan', 'tahun_masuk', 'bulan_masuk']) ? 'Export data pegawai sesuai filter aktif' : 'Export seluruh data pegawai ke Excel' }}">
                            <template x-if="!isExporting">
                                <i class="fas fa-file-excel text-emerald-600 text-xs group-hover:scale-110 transition-transform"></i>
                            </template>
                            <template x-if="isExporting">
                                <i class="fas fa-circle-notch fa-spin text-emerald-600 text-xs"></i>
                            </template>
                            <span id="tableExportBtnText" x-text="isExporting ? 'Mengekspor...' : 'Export Data'">Export Data</span>
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
                    <form action="{{ route('admin.employees.export') }}" method="GET" id="topExportSelectedForm" class="inline">
                        <template x-for="nik in selectedNiks" :key="'top-export-' + nik">
                            <input type="hidden" name="niks[]" :value="nik">
                        </template>
                        <button type="button" @click="triggerExport('topExportSelectedForm')" :disabled="isExporting"
                            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:text-primary-800 text-xs font-semibold transition shadow-2xs cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                            <template x-if="!isExporting">
                                <i class="fas fa-file-excel text-emerald-600 text-xs"></i>
                            </template>
                            <template x-if="isExporting">
                                <i class="fas fa-circle-notch fa-spin text-emerald-600 text-xs"></i>
                            </template>
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

    @include('admin.employees.partials.import-modal')

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
        <form action="{{ route('admin.employees.export') }}" method="GET" id="bottomExportSelectedForm" class="inline">
            <template x-for="nik in selectedNiks" :key="'export-' + nik">
                <input type="hidden" name="niks[]" :value="nik">
            </template>
            <button type="button" @click="triggerExport('bottomExportSelectedForm')" :disabled="isExporting"
                class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-xl bg-primary-600 hover:bg-primary-500 text-white text-xs font-semibold shadow-xs transition active:scale-95 cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                <template x-if="!isExporting">
                    <i class="fas fa-file-excel text-xs"></i>
                </template>
                <template x-if="isExporting">
                    <i class="fas fa-circle-notch fa-spin text-xs"></i>
                </template>
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

<x-toast />
@endsection
