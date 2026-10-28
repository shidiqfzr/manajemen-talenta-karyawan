@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-7xl mx-auto">
    <!-- Page Header -->
    <div class="bg-white rounded-2xl border border-surface-200/80 p-6 shadow-sm flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-2xl bg-primary-600 text-white flex items-center justify-center text-xl shadow-xs shrink-0">
                <i class="fas fa-history" aria-hidden="true"></i>
            </div>
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl sm:text-2xl font-extrabold text-surface-900 tracking-tight">Riwayat Mutasi &amp; Karir</h1>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-info-100 text-info-800 border border-info-200">
                        Audit Log
                    </span>
                </div>
                <p class="text-surface-500 text-xs sm:text-sm mt-0.5">Catatan riwayat perpindahan jabatan, promosi, rotasi internal, dan penugasan karyawan</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 w-full md:w-auto shrink-0">
            <a href="{{ route('admin.mutasi.index') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-primary-600 hover:bg-primary-700 text-white text-xs sm:text-sm font-semibold transition shadow-sm hover:shadow active:scale-95">
                <i class="fas fa-paper-plane text-xs" aria-hidden="true"></i>
                <span>Proses Mutasi Baru</span>
            </a>
            <a href="{{ route('admin.vacancies.index') }}"
                class="inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-surface-50 hover:bg-surface-100 text-surface-700 border border-surface-200 text-xs sm:text-sm font-semibold transition shadow-sm">
                <i class="fas fa-door-open text-primary-600 text-xs" aria-hidden="true"></i>
                <span>Cek Formasi Kosong</span>
            </a>
        </div>
    </div>

    @livewire('admin.mutasi-history')
</div>
@endsection
