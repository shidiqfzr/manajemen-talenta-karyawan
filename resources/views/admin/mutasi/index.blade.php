@extends('layouts.admin')

@section('content')
<div x-data="{
    activeTab: '{{ $activeTab ?? 'vacancies' }}',
    setTab(tab) {
        this.activeTab = tab;
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
}" class="space-y-6">

    <!-- Flash message from completed mutation -->
    @if (session()->has('mutasi_success'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 text-emerald-900 rounded-xl flex items-center justify-between shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-xs"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-emerald-950">Mutasi Berhasil Diproses!</h4>
                    <p class="text-xs text-emerald-700 mt-0.5">{{ session('mutasi_success') }}</p>
                </div>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800 transition p-1 cursor-pointer">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    @endif

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <!-- Breadcrumbs -->
            <nav class="flex items-center gap-2 text-xs font-medium text-surface-500 mb-1.5" aria-label="Breadcrumb">
                <span>Core HR</span>
                <i class="fas fa-chevron-right text-[10px] text-surface-400"></i>
                <span class="text-primary-700 font-semibold">Mutasi &amp; Penempatan</span>
            </nav>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-surface-900">Mutasi &amp; Penempatan</h1>
            <p class="text-sm text-surface-500 mt-0.5">Monitoring formasi kosong, Man Power Planning (MPP), dan audit riwayat karir karyawan.</p>
        </div>

        <!-- Primary Header Action: Dedicated Create Flow -->
        <div class="flex items-center gap-3 shrink-0">
            <a href="{{ route('admin.mutasi.create') }}"
                class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95 cursor-pointer">
                <i class="fas fa-plus text-xs"></i>
                <span>Buat Mutasi Baru</span>
            </a>
        </div>
    </div>

    <!-- Modern Clean Underline Tabs Navigation -->
    <div class="border-b border-surface-200">
        <nav class="-mb-px flex gap-6 sm:gap-8 overflow-x-auto" aria-label="Tabs">
            <!-- Tab 1: Monitoring Formasi -->
            <button type="button" @click="setTab('vacancies')"
                :class="activeTab === 'vacancies'
                    ? 'border-primary-600 text-primary-900 font-bold'
                    : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium'"
                class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap cursor-pointer">
                <i class="fas fa-door-open text-xs" :class="activeTab === 'vacancies' ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600'"></i>
                <span>Monitoring Formasi</span>
                <span :class="activeTab === 'vacancies' ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600 group-hover:bg-surface-200'"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums transition">
                    {{ $stats['total_vacancies'] }}
                </span>
            </button>

            <!-- Tab 2: Man Power Planning (MPP) -->
            <button type="button" @click="setTab('mpp')"
                :class="activeTab === 'mpp'
                    ? 'border-primary-600 text-primary-900 font-bold'
                    : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium'"
                class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap cursor-pointer">
                <i class="fas fa-sitemap text-xs" :class="activeTab === 'mpp' ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600'"></i>
                <span>Perencanaan Formasi (MPP)</span>
                <span :class="activeTab === 'mpp' ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-100 text-surface-600 group-hover:bg-surface-200'"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums transition">
                    444
                </span>
            </button>

            <!-- Tab 3: Riwayat & Audit Log -->
            <button type="button" @click="setTab('history')"
                :class="activeTab === 'history'
                    ? 'border-primary-600 text-primary-900 font-bold'
                    : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium'"
                class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap cursor-pointer">
                <i class="fas fa-history text-xs" :class="activeTab === 'history' ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600'"></i>
                <span>Riwayat &amp; Audit Log</span>
                <span :class="activeTab === 'history' ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600 group-hover:bg-surface-200'"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums transition">
                    {{ number_format($totalHistories) }}
                </span>
            </button>
        </nav>
    </div>

    <!-- Tab 1 Content: Monitoring Formasi Kosong (Operasional) -->
    <div x-show="activeTab === 'vacancies'" x-cloak class="space-y-6">
        <!-- Contextual Vacancy KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <!-- KPI 1: Total Formasi Kosong -->
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex items-center justify-between group">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Formasi Kosong</p>
                    <p class="text-2xl font-bold text-surface-900 tabular-nums">{{ $stats['total_vacancies'] }}</p>
                    <p class="text-xs text-primary-700 font-medium">Menunggu penugasan</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-primary-50 text-primary-700 flex items-center justify-center text-xl shrink-0 border border-primary-200">
                    <i class="fas fa-door-open"></i>
                </div>
            </div>

            <!-- KPI 2: Perlu Prioritas (>90 Hari) -->
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex items-center justify-between group">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-danger uppercase tracking-wider">Perlu Prioritas</p>
                    <p class="text-2xl font-bold text-danger tabular-nums">{{ $stats['critical_count'] }}</p>
                    <p class="text-xs text-danger font-medium">&gt; 90 hari belum terisi</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-red-50 text-danger flex items-center justify-center text-xl shrink-0 border border-red-200">
                    <i class="fas fa-triangle-exclamation"></i>
                </div>
            </div>

            <!-- KPI 3: Unit Kerja Terdampak -->
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Unit Terdampak</p>
                    <p class="text-2xl font-bold text-surface-900 tabular-nums">{{ $stats['units_affected'] }}</p>
                    <p class="text-xs text-earth-700 font-medium">Kebun, PKS &amp; Kantor Direksi</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-earth-100 text-earth-700 flex items-center justify-center text-xl shrink-0 border border-earth-300">
                    <i class="fas fa-building"></i>
                </div>
            </div>

            <!-- KPI 4: Total Riwayat Mutasi -->
            <div class="bg-white rounded-xl border border-surface-200 p-4 shadow-sm flex items-center justify-between">
                <div class="space-y-1">
                    <p class="text-xs font-semibold text-surface-500 uppercase tracking-wider">Total Riwayat SK</p>
                    <p class="text-2xl font-bold text-surface-900 tabular-nums">{{ number_format($totalHistories) }}</p>
                    <p class="text-xs text-surface-500 font-medium">Log mutasi terdata</p>
                </div>
                <div class="w-12 h-12 rounded-xl bg-surface-100 text-surface-700 flex items-center justify-center text-xl shrink-0 border border-surface-200">
                    <i class="fas fa-history"></i>
                </div>
            </div>
        </div>

        <!-- Vacancy Table -->
        @livewire('tables.job-vacancy-table')
    </div>

    <!-- Tab 2 Content: Perencanaan Formasi / MPP (Strategis) -->
    <div x-show="activeTab === 'mpp'" x-cloak class="space-y-6">
        @livewire('admin.man-power-planning')
    </div>

    <!-- Tab 3 Content: Riwayat Mutasi & Audit Log -->
    <div x-show="activeTab === 'history'" x-cloak class="space-y-6">
        @livewire('admin.mutasi-history')
    </div>

</div>
@endsection
