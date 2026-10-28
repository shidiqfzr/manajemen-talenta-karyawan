@extends('layouts.admin')

@section('content')
<div x-data="{
    activeTab: '{{ $activeTab ?? 'vacancies' }}',
    tabKeys(event) {
        const tabs = Array.from(event.currentTarget.querySelectorAll('[role=tab]'));
        const current = tabs.indexOf(document.activeElement);
        if (current === -1) return;
        let next = null;
        if (event.key === 'ArrowRight') next = (current + 1) % tabs.length;
        else if (event.key === 'ArrowLeft') next = (current - 1 + tabs.length) % tabs.length;
        else if (event.key === 'Home') next = 0;
        else if (event.key === 'End') next = tabs.length - 1;
        if (next === null) return;
        event.preventDefault();
        tabs[next].focus();
        tabs[next].click();
    },
    setTab(tab) {
        this.activeTab = tab;
        const url = new URL(window.location);
        url.searchParams.set('tab', tab);
        window.history.replaceState({}, '', url);
    }
}" class="space-y-6">

    <!-- Flash message from completed mutation -->
    @if (session()->has('mutasi_success'))
        <div role="status" class="p-4 bg-primary-50 border border-primary-200 text-primary-900 rounded-xl flex items-center justify-between shadow-xs animate-fade-in">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-primary-600 text-white flex items-center justify-center shrink-0">
                    <i class="fas fa-check text-xs" aria-hidden="true"></i>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-primary-950">Mutasi Berhasil Diproses!</h4>
                    <p class="text-xs text-primary-700 mt-0.5">{{ session('mutasi_success') }}</p>
                </div>
            </div>
            <button aria-label="Tutup notifikasi" onclick="this.parentElement.remove()" class="text-primary-500 hover:text-primary-800 transition p-1 cursor-pointer">
                <i class="fas fa-times text-xs" aria-hidden="true"></i>
            </button>
        </div>
    @endif

    <!-- Page Header & Action Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <!-- Breadcrumbs -->
            <nav class="flex items-center gap-2 text-xs font-medium text-surface-500 mb-1.5" aria-label="Breadcrumb">
                <span>Core HR</span>
                <i class="fas fa-chevron-right text-[10px] text-surface-400" aria-hidden="true"></i>
                <span class="text-primary-700 font-semibold">Mutasi &amp; Penempatan</span>
            </nav>
            <h1 class="text-2xl lg:text-3xl font-bold tracking-tight text-surface-900">Mutasi &amp; Penempatan</h1>
            <p class="text-sm text-surface-500 mt-0.5">Monitoring formasi kosong, Man Power Planning (MPP), dan audit riwayat karir karyawan.</p>
        </div>

        <!-- Primary Header Action: Dedicated Create Flow -->
        <!-- Primary Header Action: Split Dropdown Flow -->
        <div class="flex items-center gap-3 shrink-0" x-data="{ openDropdown: false }">
            <div class="inline-flex rounded-xl shadow-xs relative">
                <a href="{{ route('admin.mutasi.create') }}"
                    class="inline-flex items-center gap-2 px-4 py-2.5 rounded-l-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition cursor-pointer">
                    <i class="fas fa-plus text-xs" aria-hidden="true"></i>
                    <span>Buat Mutasi Baru</span>
                </a>
                <button aria-label="Opsi lainnya" aria-haspopup="true" :aria-expanded="openDropdown.toString()" type="button" @click="openDropdown = !openDropdown" @click.outside="openDropdown = false"
                    class="px-2.5 py-2.5 rounded-r-xl bg-primary-700 hover:bg-primary-800 text-white text-xs transition border-l border-primary-500/60 cursor-pointer flex items-center justify-center">
                    <i class="fas fa-chevron-down text-[11px] transition" :class="openDropdown ? 'rotate-180' : ''" aria-hidden="true"></i>
                </button>

                <!-- Dropdown Menu -->
                <div x-show="openDropdown" x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="transform opacity-0 scale-95"
                    x-transition:enter-end="transform opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-75"
                    x-transition:leave-start="transform opacity-100 scale-100"
                    x-transition:leave-end="transform opacity-0 scale-95"
                    class="absolute right-0 top-full mt-1.5 w-64 rounded-2xl bg-white border border-surface-200 shadow-xl py-1.5 z-30 space-y-0.5">
                    <a href="{{ route('admin.mutasi.create') }}"
                        class="flex items-start gap-3 px-3.5 py-2.5 hover:bg-surface-50 text-surface-800 transition text-xs">
                        <div class="w-7 h-7 rounded-lg bg-surface-100 text-surface-700 flex items-center justify-center shrink-0 mt-0.5">
                            <i class="fas fa-user text-xs" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="font-bold text-surface-900">Mutasi Tunggal &amp; Swap</div>
                            <div class="text-[11px] text-surface-500">Pemindahan satu pegawai ke formasi kosong atau tukar posisi 2 arah</div>
                        </div>
                    </a>
                    <div class="border-t border-surface-100 my-1"></div>
                    <a href="{{ route('admin.mutasi.chain') }}"
                        class="flex items-start gap-3 px-3.5 py-2.5 hover:bg-primary-50 text-surface-800 transition text-xs group">
                        <div class="w-7 h-7 rounded-lg bg-primary-100 text-primary-700 flex items-center justify-center shrink-0 mt-0.5 group-hover:bg-primary-600 group-hover:text-white transition">
                            <i class="fas fa-diagram-project text-xs" aria-hidden="true"></i>
                        </div>
                        <div>
                            <div class="font-bold text-primary-950 group-hover:text-primary-800">Mutasi Berantai (Kolektif)</div>
                            <div class="text-[11px] text-surface-500">Penyusunan rotasi gerbong (siklis/domino) multi-pegawai dalam 1 SK bersama</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modern Clean Underline Tabs Navigation -->
    <div class="border-b border-surface-200">
        <div role="tablist" aria-label="Bagian mutasi dan penempatan" @keydown="tabKeys($event)" class="-mb-px flex gap-6 sm:gap-8 overflow-x-auto">
            <!-- Tab 1: Monitoring Formasi -->
            <button type="button" role="tab" id="tab-vacancies" aria-controls="panel-vacancies" :aria-selected="(activeTab === 'vacancies').toString()" :tabindex="activeTab === 'vacancies' ? 0 : -1" @click="setTab('vacancies')"
                :class="activeTab === 'vacancies'
                    ? 'border-primary-600 text-primary-900 font-bold'
                    : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium'"
                class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap cursor-pointer">
                <i class="fas fa-door-open text-xs" :class="activeTab === 'vacancies' ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600'" aria-hidden="true"></i>
                <span>Monitoring Formasi</span>
                <span :class="activeTab === 'vacancies' ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600 group-hover:bg-surface-200'"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums transition">
                    {{ $stats['total_vacancies'] }}
                </span>
            </button>

            <!-- Tab 2: Man Power Planning (MPP) -->
            <button type="button" role="tab" id="tab-mpp" aria-controls="panel-mpp" :aria-selected="(activeTab === 'mpp').toString()" :tabindex="activeTab === 'mpp' ? 0 : -1" @click="setTab('mpp')"
                :class="activeTab === 'mpp'
                    ? 'border-primary-600 text-primary-900 font-bold'
                    : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium'"
                class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap cursor-pointer">
                <i class="fas fa-sitemap text-xs" :class="activeTab === 'mpp' ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600'" aria-hidden="true"></i>
                <span>Perencanaan Formasi (MPP)</span>
                <span :class="activeTab === 'mpp' ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600 group-hover:bg-surface-200'"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums transition">
                    444
                </span>
            </button>

            <!-- Tab 3: Riwayat & Audit Log -->
            <button type="button" role="tab" id="tab-history" aria-controls="panel-history" :aria-selected="(activeTab === 'history').toString()" :tabindex="activeTab === 'history' ? 0 : -1" @click="setTab('history')"
                :class="activeTab === 'history'
                    ? 'border-primary-600 text-primary-900 font-bold'
                    : 'border-transparent text-surface-500 hover:text-surface-800 hover:border-surface-300 font-medium'"
                class="group inline-flex items-center gap-2 py-3 px-1 border-b-2 text-xs sm:text-sm transition whitespace-nowrap cursor-pointer">
                <i class="fas fa-history text-xs" :class="activeTab === 'history' ? 'text-primary-600' : 'text-surface-400 group-hover:text-surface-600'" aria-hidden="true"></i>
                <span>Riwayat &amp; Audit Log</span>
                <span :class="activeTab === 'history' ? 'bg-primary-100 text-primary-800' : 'bg-surface-100 text-surface-600 group-hover:bg-surface-200'"
                    class="px-2 py-0.5 rounded-full text-xs font-semibold tabular-nums transition">
                    {{ number_format($totalHistories) }}
                </span>
            </button>
        </div>
    </div>

    <!-- Tab 1 Content: Monitoring Formasi Kosong (Operasional) -->
    <div x-show="activeTab === 'vacancies'" role="tabpanel" id="panel-vacancies" aria-labelledby="tab-vacancies" tabindex="0" x-cloak class="space-y-6">
        <!-- Contextual Vacancy KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
            <x-admin.kpi-card
                title="Formasi Kosong"
                :value="$stats['total_vacancies']"
                subtitle="Menunggu penugasan"
                icon="fas fa-door-open"
                theme="primary"
                :coloredValue="false"
            />
            <x-admin.kpi-card
                title="Perlu Prioritas"
                :value="$stats['critical_count']"
                subtitle="> 90 hari belum terisi"
                icon="fas fa-triangle-exclamation"
                theme="danger"
                :coloredValue="true"
            />
            <x-admin.kpi-card
                title="Unit Terdampak"
                :value="$stats['units_affected']"
                subtitle="Kebun, PKS & Kantor Direksi"
                icon="fas fa-building"
                theme="earth"
                :coloredValue="false"
            />
            <x-admin.kpi-card
                title="Total Riwayat SK"
                :value="$totalHistories"
                subtitle="Log mutasi terdata"
                icon="fas fa-history"
                theme="neutral"
                :coloredValue="false"
            />
        </div>

        <!-- Vacancy Table -->
        @livewire('tables.job-vacancy-table')
    </div>

    <!-- Tab 2 Content: Perencanaan Formasi / MPP (Strategis) -->
    <div x-show="activeTab === 'mpp'" role="tabpanel" id="panel-mpp" aria-labelledby="tab-mpp" tabindex="0" x-cloak class="space-y-6">
        @livewire('admin.man-power-planning')
    </div>

    <!-- Tab 3 Content: Riwayat Mutasi & Audit Log -->
    <div x-show="activeTab === 'history'" role="tabpanel" id="panel-history" aria-labelledby="tab-history" tabindex="0" x-cloak class="space-y-6">
        @livewire('admin.mutasi-history')
    </div>

</div>
@endsection
