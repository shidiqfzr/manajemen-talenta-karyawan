@extends('layouts.admin')

@section('content')
<div x-data="{
    importModalOpen: false,
    deleteModalOpen: false,
    showColumnSpecs: false,
    showAdvancedFilters: {{ request()->hasAny(['unit_kerja', 'jabatan', 'rm_level', 'tahun_masuk']) ? 'true' : 'false' }},
    deleteNik: '',
    deleteNama: '',
    deleteAction: '',
    copiedNik: null,
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

                    <div x-show="showErrors" x-cloak class="mt-2.5 space-y-1.5 bg-red-50/60 border border-red-200/80 rounded-lg p-3 text-xs text-red-900 max-h-48 overflow-y-auto">
                        @foreach($summary['errors'] as $error)
                            <div class="flex items-start gap-2">
                                <i class="fas fa-circle-xmark text-danger mt-0.5 text-[11px] shrink-0"></i>
                                <span>{{ $error }}</span>
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

        <!-- Table Information & Smart Export Toolbar -->
        <div class="px-5 py-3.5 bg-white border-b border-surface-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
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

            <!-- Right: Smart 1-Click Export to Excel in Table Toolbar -->
            <div>
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

        <!-- Data Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-surface-50 border-b border-surface-200 text-xs font-semibold text-surface-700 uppercase tracking-wider">
                        <th scope="col" class="py-3.5 px-5">Pegawai</th>
                        <th scope="col" class="py-3.5 px-5">Jabatan &amp; Unit Kerja</th>
                        <th scope="col" class="py-3.5 px-5">Eselon &amp; Level</th>
                        <th scope="col" class="py-3.5 px-5">Golongan</th>
                        <th scope="col" class="py-3.5 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-200 text-sm">
                    @forelse ($employees as $employee)
                        <tr @click="window.location.href = '{{ route('admin.employees.show', $employee->nik) }}'"
                            class="cursor-pointer hover:bg-slate-50/80 transition duration-150 group {{ $employee->concurrentJobs->isNotEmpty() ? 'border-l-4 border-l-purple-500 bg-purple-50/15' : '' }}">
                            <!-- Pegawai (Avatar + Name + NIK) -->
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <div class="flex items-center gap-3">
                                    <!-- Photo or Initials Avatar -->
                                    <div class="w-10 h-10 rounded-full shrink-0 overflow-hidden bg-primary-100 text-primary-800 flex items-center justify-center font-bold text-xs border border-primary-200 shadow-xs">
                                        @if ($employee->foto && Storage::disk('public')->exists($employee->foto))
                                            <img src="{{ Storage::disk('public')->url($employee->foto) }}" alt="{{ $employee->nama }}"
                                                class="w-full h-full object-cover">
                                        @else
                                            @php
                                                $initials = collect(explode(' ', $employee->nama))
                                                    ->take(2)
                                                    ->map(fn($part) => strtoupper(substr($part, 0, 1)))
                                                    ->implode('');
                                            @endphp
                                            <span>{{ $initials ?: 'HR' }}</span>
                                        @endif
                                    </div>
                                    <div class="min-w-0">
                                        <a href="{{ route('admin.employees.show', $employee->nik) }}"
                                            class="font-semibold text-surface-900 group-hover:text-primary-700 transition block truncate max-w-[200px] sm:max-w-xs"
                                            title="{{ $employee->nama }}">
                                            <x-highlight :text="$employee->nama" />
                                        </a>
                                        <div class="flex items-center gap-1.5 mt-0.5">
                                            <span class="font-mono text-xs text-surface-500 tabular-nums">
                                                <x-highlight :text="$employee->nik" />
                                            </span>
                                            <button type="button" @click.stop="copyNik('{{ $employee->nik }}')"
                                                class="text-surface-400 hover:text-surface-700 text-[11px] transition"
                                                title="Salin NIK">
                                                <i :class="copiedNik === '{{ $employee->nik }}' ? 'fas fa-check text-primary-600' : 'far fa-copy'"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </td>

                            <!-- Jabatan & Unit Kerja -->
                            <td class="py-3.5 px-5">
                                <div class="font-medium text-surface-900 text-sm flex items-center gap-2 flex-wrap">
                                    <span><x-highlight :text="$employee->jabatan" /></span>

                                    {{-- Badge Kasus Spesial 1: Sanksi Indisipliner Aktif / Kedaluwarsa --}}
                                    @if($employee->isSanctionActive())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-red-50 text-red-700 border border-red-200 shadow-2xs"
                                            title="Pegawai dalam masa sanksi indisipliner (Diperban) hingga {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }}">
                                            <i class="fas fa-gavel text-[9px]"></i>
                                            Masa Sanksi s/d {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }}
                                        </span>
                                    @elseif($employee->isSanctionExpired())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200 shadow-2xs animate-pulse"
                                            title="Masa sanksi kedaluwarsa sejak {{ $employee->tanggal_berakhir_sanksi?->format('d M Y') }} - Harap terbitkan Mutasi Pencabutan Sanksi">
                                            <i class="fas fa-exclamation-triangle text-[9px] text-amber-600"></i>
                                            Sanksi Kedaluwarsa
                                        </span>
                                    @endif

                                    {{-- Badge Kasus Spesial 2: Jabatan Overflow / Pool Penampungan (Advisor) --}}
                                    @if($employee->isOverflow())
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-700 border border-slate-200 shadow-2xs"
                                            title="Formasi penampungan fungsional non-struktural (Advisor/Overflow Pool)">
                                            <i class="fas fa-layer-group text-[9px] text-slate-500"></i>
                                            Fungsional Pool
                                        </span>
                                    @endif
                                </div>
                                @if($employee->concurrentJobs->isNotEmpty())
                                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                        @foreach($employee->concurrentJobs as $cj)
                                            @php
                                                $badgeStyle = 'bg-purple-50 text-purple-700 border-purple-200';
                                                if ($cj->status_penugasan === 'Plt') {
                                                    $badgeStyle = 'bg-blue-50 text-blue-700 border-blue-200';
                                                } elseif ($cj->status_penugasan === 'Diperbantukan') {
                                                    $badgeStyle = 'bg-amber-50 text-amber-700 border-amber-200';
                                                }
                                            @endphp
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border {{ $badgeStyle }}"
                                                title="Tugas Tambahan Aktif: {{ $cj->jabatan }} di {{ $cj->unit_kerja }} (sejak {{ $cj->tmt_awal?->format('d M Y') }})">
                                                <i class="fas fa-briefcase text-[9px]"></i>
                                                {{ $cj->status_penugasan }}: {{ $cj->jabatan }} ({{ $cj->unit_kerja }})
                                            </span>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="text-xs text-surface-500 flex items-center gap-1.5 mt-0.5">
                                    <i class="fas fa-building text-[10px] text-surface-400"></i>
                                    <span class="truncate max-w-[280px]"><x-highlight :text="$employee->unit_kerja" /></span>
                                </div>
                            </td>

                            <!-- Eselon & Level -->
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    @if($employee->rm_level)
                                        @php
                                            $rmTheme = match($employee->rm_level) {
                                                'RM-1' => 'bg-purple-50 text-purple-700 border-purple-200 font-bold',
                                                'RM-2' => 'bg-sky-50 text-sky-700 border-sky-200 font-bold',
                                                'RM-3' => 'bg-emerald-50 text-emerald-700 border-emerald-200 font-bold',
                                                default => 'bg-slate-100 text-slate-700 border-slate-200 font-medium',
                                            };
                                        @endphp
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] border {{ $rmTheme }}"
                                            title="{{ \App\Models\Employee::RM_LEVELS[$employee->rm_level] ?? $employee->rm_level }}">
                                            {{ $employee->rm_level }}
                                        </span>
                                    @endif

                                    @php
                                        $isKarpim = str_contains(strtolower($employee->level ?? ''), 'karpim') || str_contains(strtolower($employee->level ?? ''), 'pimpinan');
                                    @endphp
                                    @if($employee->level)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium {{ $isKarpim ? 'bg-primary-50 text-primary-800 border border-primary-200' : 'bg-surface-100 text-surface-700 border border-surface-200' }}">
                                            {{ $employee->level }}
                                        </span>
                                    @else
                                        <span class="text-surface-400 text-xs">-</span>
                                    @endif
                                </div>
                            </td>

                            <!-- Golongan -->
                            <td class="py-3.5 px-5 whitespace-nowrap">
                                @if($employee->golongan)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-xs font-semibold bg-earth-100 text-earth-800 border border-earth-300 tabular-nums">
                                        {{ $employee->golongan }}
                                    </span>
                                @else
                                    <span class="text-surface-400 text-xs">-</span>
                                @endif
                            </td>

                            <!-- Aksi -->
                            <td class="py-3.5 px-5 whitespace-nowrap text-right">
                                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-white shadow-2xs divide-x divide-slate-100 overflow-hidden" @click.stop>
                                    <!-- Detail -->
                                    <a href="{{ route('admin.employees.show', $employee->nik) }}"
                                        class="p-2 text-slate-500 hover:text-primary-700 hover:bg-slate-50 transition"
                                        title="Lihat Detail Profil">
                                        <i class="fas fa-eye text-xs"></i>
                                    </a>

                                    <!-- Edit -->
                                    <a href="{{ route('admin.employees.edit', $employee->nik) }}"
                                        class="p-2 text-slate-500 hover:text-earth-700 hover:bg-slate-50 transition"
                                        title="Ubah Data Pegawai">
                                        <i class="fas fa-pencil-alt text-xs"></i>
                                    </a>

                                    <!-- Delete Modal Trigger -->
                                    <button type="button"
                                        @click="confirmDelete('{{ $employee->nik }}', '{{ addslashes($employee->nama) }}', '{{ route('admin.employees.destroy', $employee->nik) }}')"
                                        class="p-2 text-slate-500 hover:text-danger hover:bg-red-50 transition"
                                        title="Hapus Pegawai">
                                        <i class="fas fa-trash-alt text-xs"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-16 text-center">
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

        <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
            <div x-show="importModalOpen"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-surface-200">
                
                <!-- Modal Header -->
                <div class="px-6 py-5 border-b border-surface-200 flex items-center justify-between bg-surface-50/75">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary-100 text-primary-700 flex items-center justify-center text-lg border border-primary-200">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div>
                            <h3 class="text-base font-bold text-surface-900" id="modal-title">Import Data Pegawai</h3>
                            <p class="text-xs text-surface-500 mt-0.5">Unggah spreadsheet Excel (.xlsx, .xls) untuk penambahan atau pembaruan massal.</p>
                        </div>
                    </div>
                    <button type="button" @click="importModalOpen = false"
                        class="p-2 text-surface-400 hover:text-surface-700 rounded-lg hover:bg-surface-100 transition">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <!-- Modal Body -->
                <div class="px-6 py-5 space-y-4 max-h-[75vh] overflow-y-auto">
                    
                    <!-- Step 1: Download Template Card -->
                    <div class="bg-primary-50/70 border border-primary-200 rounded-xl p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                        <div class="space-y-1 flex-1">
                            <div class="flex items-center gap-2">
                                <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">1</span>
                                <h4 class="text-xs font-bold text-primary-950">Gunakan Format Template Resmi</h4>
                            </div>
                            <p class="text-xs text-surface-600 pl-7">
                                Pastikan susunan kolom sesuai dengan format baku sistem agar proses verifikasi berjalan lancar.
                            </p>
                        </div>
                        <a href="{{ route('admin.employees.downloadTemplate') }}"
                            class="shrink-0 inline-flex items-center gap-2 px-3.5 py-2 rounded-lg bg-white border border-primary-300 text-primary-800 text-xs font-bold hover:bg-primary-100 transition shadow-xs group">
                            <i class="fas fa-download text-primary-600 group-hover:translate-y-0.5 transition-transform"></i>
                            <span>Unduh Template Excel</span>
                        </a>
                    </div>

                    <!-- Collapsible Column Format Specs -->
                    <div class="border border-surface-200 rounded-xl overflow-hidden">
                        <button type="button" @click="showColumnSpecs = !showColumnSpecs"
                            class="w-full px-4 py-2.5 bg-surface-50 hover:bg-surface-100 text-left flex items-center justify-between transition">
                            <div class="flex items-center gap-2 text-xs font-bold text-surface-800">
                                <i class="fas fa-table-columns text-primary-700 text-xs"></i>
                                <span>Spesifikasi Header Kolom Excel</span>
                                <span class="px-2 py-0.5 rounded-full bg-surface-200 text-[10px] text-surface-700 font-semibold">5 Wajib, 15 Opsional</span>
                            </div>
                            <i class="fas fa-chevron-down text-xs text-surface-400 transition-transform duration-200" :class="{ 'rotate-180': showColumnSpecs }"></i>
                        </button>

                        <div x-show="showColumnSpecs" x-cloak class="p-4 bg-white border-t border-surface-200 text-xs space-y-3">
                            <div>
                                <p class="font-bold text-surface-800 mb-1.5 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-danger"></span> Kolom Wajib Diisi:
                                </p>
                                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                                    <div class="p-2 bg-red-50/50 border border-red-200 rounded-md">
                                        <code class="font-bold text-danger text-[11px]">nik</code>
                                        <p class="text-[10px] text-surface-500">Unik / Kunci Utama</p>
                                    </div>
                                    <div class="p-2 bg-red-50/50 border border-red-200 rounded-md">
                                        <code class="font-bold text-danger text-[11px]">nama</code>
                                        <p class="text-[10px] text-surface-500">Nama Lengkap</p>
                                    </div>
                                    <div class="p-2 bg-red-50/50 border border-red-200 rounded-md">
                                        <code class="font-bold text-danger text-[11px]">jabatan</code>
                                        <p class="text-[10px] text-surface-500">Formasi Jabatan</p>
                                    </div>
                                    <div class="p-2 bg-red-50/50 border border-red-200 rounded-md">
                                        <code class="font-bold text-danger text-[11px]">level</code>
                                        <p class="text-[10px] text-surface-500">Karpim / Pelaksana</p>
                                    </div>
                                    <div class="p-2 bg-red-50/50 border border-red-200 rounded-md">
                                        <code class="font-bold text-danger text-[11px]">unit_kerja</code>
                                        <p class="text-[10px] text-surface-500">Nama Unit / Kebun</p>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-surface-100">
                                <p class="font-bold text-surface-800 mb-1.5 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-surface-400"></span> Kolom Opsional:
                                </p>
                                <p class="text-[11px] text-surface-500 leading-relaxed font-mono">
                                    golongan, tanggal_dalam_jabatan, tmt_unit_kerja, tempat_lahir, tanggal_lahir, tmt_bekerja, tanggal_diangkat_staf, susunan_keluarga, job_grade, person_grade, tanggal_mbt, tanggal_pensiun, agama, pendidikan_terakhir, sekolah.
                                </p>
                                <p class="text-[10px] text-amber-700 mt-1 italic">
                                    * Catatan: Format tanggal disarankan YYYY-MM-DD atau format Date baku Excel.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Upload File Area -->
                    <div class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="w-5 h-5 rounded-full bg-primary-600 text-white text-[11px] font-bold flex items-center justify-center shrink-0">2</span>
                            <label class="text-xs font-bold text-surface-900">Pilih Berkas Excel untuk Diunggah</label>
                        </div>

                        <form action="{{ route('admin.employees.import') }}" method="POST" enctype="multipart/form-data" id="modalImportForm">
                            @csrf
                            <div id="modalDropZone"
                                class="relative border-2 border-dashed border-surface-300 hover:border-primary-500 rounded-xl p-6 text-center cursor-pointer transition bg-surface-50/50 hover:bg-primary-50/30 group">
                                <input type="file" name="import_file" id="modal_import_file"
                                    accept=".xls,.xlsx" required
                                    class="absolute inset-0 w-full h-full opacity-0 cursor-pointer"
                                    onchange="handleModalFileChange(this)">

                                <div id="modalFileDefault">
                                    <div class="w-12 h-12 rounded-full bg-surface-100 text-surface-500 group-hover:bg-primary-100 group-hover:text-primary-700 flex items-center justify-center mx-auto text-xl transition">
                                        <i class="fas fa-cloud-arrow-up"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-surface-800 mt-3">Klik untuk memilih file atau seret file ke sini</p>
                                    <p class="text-xs text-surface-400 mt-1">Format Excel (.xlsx, .xls) &bull; Ukuran maksimal 10MB</p>
                                </div>

                                <div id="modalFileSelected" class="hidden">
                                    <div class="w-12 h-12 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center mx-auto text-xl">
                                        <i class="fas fa-file-circle-check"></i>
                                    </div>
                                    <p class="text-sm font-semibold text-surface-900 mt-3 truncate max-w-xs mx-auto" id="modalFileName"></p>
                                    <p class="text-xs text-emerald-700 mt-1 font-medium">Berkas valid dan siap diproses</p>
                                    <button type="button" onclick="clearModalFile(event)" class="mt-2 text-[11px] text-danger hover:underline">
                                        Ganti Berkas
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                </div>

                <!-- Modal Footer -->
                <div class="px-6 py-4 bg-surface-50 border-t border-surface-200 flex items-center justify-end gap-2.5">
                    <button type="button" @click="importModalOpen = false"
                        class="px-4 py-2.5 rounded-lg border border-surface-300 bg-white text-surface-700 hover:bg-surface-100 text-xs sm:text-sm font-medium transition shadow-xs">
                        Batal
                    </button>
                    <button type="submit" form="modalImportForm" id="modalImportSubmitBtn"
                        class="inline-flex items-center gap-2 px-5 py-2.5 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm">
                        <i class="fas fa-upload text-xs" id="modalImportIcon"></i>
                        <span id="modalImportBtnText">Mulai Import Data</span>
                    </button>
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

</div>

<!-- Enhanced JS for File Upload, Drag & Drop & Table Export -->
<script>
    function handleModalFileChange(input) {
        const fileDefault = document.getElementById('modalFileDefault');
        const fileSelected = document.getElementById('modalFileSelected');
        const fileName = document.getElementById('modalFileName');

        if (input.files && input.files[0]) {
            fileName.textContent = input.files[0].name + ' (' + (input.files[0].size / 1024).toFixed(1) + ' KB)';
            fileDefault.classList.add('hidden');
            fileSelected.classList.remove('hidden');
        } else {
            fileDefault.classList.remove('hidden');
            fileSelected.classList.add('hidden');
        }
    }

    function clearModalFile(e) {
        e.preventDefault();
        e.stopPropagation();
        const fileInput = document.getElementById('modal_import_file');
        fileInput.value = '';
        handleModalFileChange(fileInput);
    }

    // Modal Import Submit State
    document.getElementById('modalImportForm')?.addEventListener('submit', function() {
        const btn = document.getElementById('modalImportSubmitBtn');
        const btnText = document.getElementById('modalImportBtnText');

        btn.disabled = true;
        btnText.textContent = 'Memproses data...';
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
