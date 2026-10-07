@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Page Header Card -->
    <div class="bg-white rounded-xl border border-surface-200 p-6 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-gradient-to-br from-primary-600 to-emerald-700 text-white flex items-center justify-center text-xl shadow-md shadow-primary-900/10 shrink-0">
                <i class="fas fa-paper-plane"></i>
            </div>
            <div>
                <!-- Breadcrumbs -->
                <nav class="flex items-center gap-2 text-xs font-medium text-surface-500 mb-1" aria-label="Breadcrumb">
                    <span>Core HR</span>
                    <i class="fas fa-chevron-right text-[10px] text-surface-400"></i>
                    <a href="{{ route('admin.mutasi.index') }}" class="text-surface-600 hover:text-primary-700 transition">Mutasi &amp; Penempatan</a>
                    <i class="fas fa-chevron-right text-[10px] text-surface-400"></i>
                    <span class="text-primary-700 font-semibold">Buat Mutasi Baru</span>
                </nav>
                <h1 class="text-xl sm:text-2xl font-bold text-surface-900 tracking-tight">Buat Mutasi &amp; Penempatan Baru</h1>
                <p class="text-surface-500 text-xs sm:text-sm mt-0.5">Penugasan karyawan ke formasi kosong, rotasi berkala, atau promosi struktural</p>
            </div>
        </div>

        <!-- Back to Hub Button -->
        <a href="{{ route('admin.mutasi.index') }}"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg border border-surface-200 text-surface-700 hover:bg-surface-50 text-xs sm:text-sm font-semibold transition shadow-xs">
            <i class="fas fa-arrow-left text-xs text-surface-400"></i>
            <span>Kembali ke Hub Mutasi</span>
        </a>
    </div>

    <!-- Vacancy Closure Alert Banner (If prefilled from Monitoring Formasi Kosong) -->
    @if (request('jabatan') && request('unit'))
        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-xl shadow-xs flex items-start gap-3.5 animate-fade-in">
            <div class="w-8 h-8 rounded-lg bg-emerald-600 text-white flex items-center justify-center text-sm shrink-0 mt-0.5">
                <i class="fas fa-door-closed"></i>
            </div>
            <div class="text-xs text-emerald-950 space-y-1">
                <h4 class="font-bold text-emerald-900 text-sm">Penugasan Pengisian Formasi Kosong</h4>
                <p class="leading-relaxed">
                    Anda sedang melakukan penugasan mutasi internal untuk mengisi formasi kosong:
                    <strong class="text-surface-900 font-bold">{{ request('jabatan') }}</strong> di unit <strong class="text-surface-900 font-bold">{{ request('unit') }}</strong>.
                </p>
                <p class="text-emerald-700 font-medium">
                    <i class="fas fa-check-circle text-xs mr-1"></i> Setelah SK mutasi diterbitkan, posisi lowong ini akan otomatis terisi dan diperbarui di dashboard monitoring.
                </p>
            </div>
        </div>
    @endif

    <!-- Main Smart Assignment Form -->
    <div class="bg-white rounded-xl border border-surface-200 shadow-sm p-6">
        @livewire('admin.mutasi-process', ['jabatan' => request('jabatan'), 'unit' => request('unit')])
    </div>

</div>
@endsection
