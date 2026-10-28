@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-primary-500 to-teal-600 text-white flex items-center justify-center text-xl shadow-md shadow-primary-500/20 shrink-0">
                <i class="fas fa-briefcase" aria-hidden="true"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-surface-900 tracking-tight">Monitoring Jabatan Kosong</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-primary-100 text-primary-800 border border-primary-200">
                        {{ $stats['total_vacancies'] }} Formasi
                    </span>
                </div>
                <p class="text-surface-500 text-xs sm:text-sm mt-0.5">Pantau posisi lowong akibat mutasi, promosi, atau rotasi untuk segera diisi atau ditentukan suksesi</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 w-full md:w-auto shrink-0">
            <a href="{{ route('admin.mutasi.index') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95">
                <i class="fas fa-paper-plane text-xs" aria-hidden="true"></i>
                <span>Proses Mutasi Baru</span>
            </a>
            <a href="{{ route('admin.mutasi.history') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-surface-50 hover:bg-surface-100 text-surface-700 border border-surface-200 text-xs sm:text-sm font-semibold transition shadow-sm">
                <i class="fas fa-history text-surface-400 text-xs" aria-hidden="true"></i>
                <span>Riwayat Log</span>
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <x-admin.kpi-card
            title="Total Formasi Kosong"
            :value="$stats['total_vacancies']"
            subtitle="Menunggu penugasan baru"
            icon="fas fa-door-open"
            theme="primary"
            :coloredValue="false"
        />

        <x-admin.kpi-card
            title="Unit Terdampak"
            :value="$stats['units_affected']"
            subtitle="Kebun, PKS & Kantor Direksi"
            icon="fas fa-building"
            theme="teal"
            :coloredValue="false"
        />

        <x-admin.kpi-card
            title="Perlu Prioritas"
            :value="$stats['critical_count']"
            subtitle="Prioritas penempatan staf"
            icon="fas fa-triangle-exclamation"
            theme="danger"
            :badge="'> 90 Hari'"
            :coloredValue="true"
        />

        <x-admin.kpi-card
            title="Lowongan Terlama"
            :value="$stats['longest_vacant'] ? number_format($stats['longest_vacant']->days_vacant) : '0'"
            icon="fas fa-clock"
            theme="earth"
            :badge="$stats['longest_vacant'] ? 'Hari Kosong' : null"
            :coloredValue="true"
        >
            @if ($stats['longest_vacant'])
                <p class="text-xs font-semibold text-surface-700 truncate" title="{{ $stats['longest_vacant']->jabatan }}">
                    {{ $stats['longest_vacant']->jabatan }}
                </p>
                <p class="text-[11px] text-surface-500 truncate mt-0.5" title="{{ $stats['longest_vacant']->unit_kerja }}">
                    {{ $stats['longest_vacant']->unit_kerja }}
                </p>
            @else
                <p class="text-xs text-surface-500">Semua formasi terisi penuh</p>
            @endif
        </x-admin.kpi-card>
    </div>

    <!-- Livewire Interactive Table -->
    @livewire('tables.job-vacancy-table')
</div>
@endsection
