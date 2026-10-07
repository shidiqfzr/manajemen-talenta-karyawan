@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-br from-emerald-500 to-teal-600 text-white flex items-center justify-center text-xl shadow-md shadow-emerald-500/20 shrink-0">
                <i class="fas fa-briefcase"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 tracking-tight">Monitoring Jabatan Kosong</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 border border-emerald-200">
                        {{ $stats['total_vacancies'] }} Formasi
                    </span>
                </div>
                <p class="text-slate-500 text-xs sm:text-sm mt-0.5">Pantau posisi lowong akibat mutasi, promosi, atau rotasi untuk segera diisi atau ditentukan suksesi</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 w-full md:w-auto shrink-0">
            <a href="{{ route('admin.mutasi.index') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95">
                <i class="fas fa-paper-plane text-xs"></i>
                <span>Proses Mutasi Baru</span>
            </a>
            <a href="{{ route('admin.mutasi.history') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs sm:text-sm font-semibold transition shadow-sm">
                <i class="fas fa-history text-slate-400 text-xs"></i>
                <span>Riwayat Log</span>
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
        <!-- Card 1: Total Lowongan -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-blue-50/50 rounded-full blur-2xl -mr-6 -mt-6 group-hover:bg-blue-100/50 transition"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Formasi Kosong</span>
                <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-base font-bold">
                    <i class="fas fa-door-open"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $stats['total_vacancies'] }}</span>
                <span class="text-xs font-semibold text-slate-500">Jabatan</span>
            </div>
            <p class="text-[12px] text-slate-500 mt-1.5 flex items-center gap-1.5">
                <i class="fas fa-check-circle text-blue-500 text-xs"></i>
                Menunggu penugasan pejabat baru
            </p>
        </div>

        <!-- Card 2: Unit Kerja Terdampak -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-teal-50/50 rounded-full blur-2xl -mr-6 -mt-6 group-hover:bg-teal-100/50 transition"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Unit Terdampak</span>
                <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-600 flex items-center justify-center text-base font-bold">
                    <i class="fas fa-building"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-slate-900 tracking-tight">{{ $stats['units_affected'] }}</span>
                <span class="text-xs font-semibold text-slate-500">Unit / Kebun</span>
            </div>
            <p class="text-[12px] text-slate-500 mt-1.5 flex items-center gap-1.5">
                <i class="fas fa-map-marker-alt text-teal-500 text-xs"></i>
                Kebun, Pabrik, & Kantor Direksi
            </p>
        </div>

        <!-- Card 3: Prioritas Kritis (>90 Hari) -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-red-50/50 rounded-full blur-2xl -mr-6 -mt-6 group-hover:bg-red-100/50 transition"></div>
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-bold text-red-600 uppercase tracking-wider">Perlu Prioritas</span>
                <div class="w-10 h-10 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-base font-bold">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
            </div>
            <div class="flex items-baseline gap-2">
                <span class="text-3xl font-extrabold text-red-600 tracking-tight">{{ $stats['critical_count'] }}</span>
                <span class="text-xs font-semibold text-red-500">&gt; 90 Hari</span>
            </div>
            <p class="text-[12px] text-red-600 font-medium mt-1.5 flex items-center gap-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                Prioritas tinggi penempatan staf
            </p>
        </div>

        <!-- Card 4: Lowongan Terlama -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-5 shadow-sm hover:shadow-md transition-shadow relative overflow-hidden group">
            <div class="absolute top-0 right-0 w-24 h-24 bg-amber-50/50 rounded-full blur-2xl -mr-6 -mt-6 group-hover:bg-amber-100/50 transition"></div>
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-bold text-amber-700 uppercase tracking-wider">Lowongan Terlama</span>
                <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-base font-bold">
                    <i class="fas fa-clock"></i>
                </div>
            </div>
            @if ($stats['longest_vacant'])
                <div class="flex items-baseline gap-1.5">
                    <span class="text-2xl font-extrabold text-amber-700 tracking-tight">{{ number_format($stats['longest_vacant']->days_vacant) }}</span>
                    <span class="text-xs font-bold text-amber-800">Hari Kosong</span>
                </div>
                <div class="mt-1">
                    <p class="text-xs font-semibold text-slate-800 truncate" title="{{ $stats['longest_vacant']->jabatan }}">
                        {{ $stats['longest_vacant']->jabatan }}
                    </p>
                    <p class="text-[11px] text-slate-500 truncate mt-0.5" title="{{ $stats['longest_vacant']->unit_kerja }}">
                        {{ $stats['longest_vacant']->unit_kerja }}
                    </p>
                </div>
            @else
                <div class="text-2xl font-extrabold text-slate-400 mt-1">Nihil</div>
                <p class="text-xs text-slate-400 mt-1">Semua formasi terisi penuh</p>
            @endif
        </div>
    </div>

    <!-- Livewire Interactive Table -->
    @livewire('tables.job-vacancy-table')
</div>
@endsection
