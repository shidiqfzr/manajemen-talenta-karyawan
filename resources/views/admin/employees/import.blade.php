@extends('layouts.admin')

@section('content')
@php
    // Jumlah kolom mengikuti kamus kolom (satu sumber), bukan angka tetap pada teks.
    $guideTotal = collect($dictionaryCategories)->flatMap(fn ($category) => $category['columns'])->count();
@endphp
<div x-data="employeeImportWizard()" class="space-y-6 max-w-7xl mx-auto pb-12">
    {{-- MODAL PROSES LATAR BELAKANG & INSPEKSI PERUBAHAN --}}
    @include('admin.employees.partials.import-async-modal')
    @include('admin.employees.partials.import-diff-modal')

    <!-- Header Standar Enterprise -->
    <x-admin.page-header 
        title="Impor Data Karyawan"
        subtitle="Alur unggah massal data induk karyawan dengan validasi multi-tahap, pratinjau analisis perubahan, dan mitigasi kesalahan."
        icon="fas fa-file-excel"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Daftar Karyawan', 'url' => route('admin.employees.index')],
            ['label' => 'Impor Data Spreadsheet']
        ]"
        back-url="{{ route('admin.employees.index') }}"
        back-label="Kembali ke Direktori"
    >
        <x-admin.button href="{{ route('admin.employees.import.batches.index') }}" variant="secondary" icon="fas fa-clock-rotate-left">
            Riwayat &amp; Persetujuan
            @if ($pendingApprovalCount > 0)
                <span class="ml-1 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full bg-warning-100 text-warning-800 text-xs font-semibold" aria-label="{{ $pendingApprovalCount }} pengajuan menunggu persetujuan">{{ $pendingApprovalCount }}</span>
            @endif
        </x-admin.button>
    </x-admin.page-header>

    {{-- Umpan balik gagal: tidak menutup otomatis agar pesan dapat dibaca lengkap (DESIGN.md §7.7) --}}
    <div x-show="errorMessage" x-cloak role="alert" class="flex items-start gap-3 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-800">
        <i class="fas fa-exclamation-circle mt-0.5" aria-hidden="true"></i>
        <p class="flex-1 whitespace-pre-line" x-text="errorMessage"></p>
        <button type="button" @click="errorMessage = ''" aria-label="Tutup pesan galat" class="text-danger-700 hover:text-danger-800 focus-visible:ring-2 focus-visible:ring-primary-500 rounded">
            <i class="fas fa-times" aria-hidden="true"></i>
        </button>
    </div>

    <!-- ============================================================ -->
    {{-- ENTERPRISE STEPPER PROGRESS BAR (DESIGN.md Bab 6.6)          --}}
    <!-- ============================================================ -->
    <div class="bg-white rounded-2xl border border-surface-200/80 p-4 sm:p-5 shadow-xs">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative">
            <!-- Step 1: Upload & Format Kolom -->
            <div class="flex items-center gap-3.5 p-3 rounded-xl transition"
                :class="currentStep === 1 ? 'bg-primary-50/80 border border-primary-200/80 shadow-2xs' : (currentStep > 1 ? 'bg-surface-50 border border-surface-100' : 'bg-transparent border border-transparent opacity-60')">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition"
                    :class="currentStep === 1 ? 'bg-primary-600 text-white shadow-sm ring-4 ring-primary-100' : (currentStep > 1 ? 'bg-primary-700 text-white' : 'bg-surface-200 text-surface-600')">
                    <template x-if="currentStep > 1">
                        <i class="fas fa-check" aria-hidden="true"></i>
                    </template>
                    <template x-if="currentStep <= 1">
                        <span>1</span>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition"
                        :class="currentStep === 1 ? 'text-primary-800' : (currentStep > 1 ? 'text-surface-700' : 'text-surface-500')">
                        Tahap 1
                    </p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-surface-900 truncate">Unggah & Panduan Kolom</h4>
                    <p class="text-[11px] text-surface-500 truncate">Pilih spreadsheet & format {{ $guideTotal }} kolom</p>
                </div>
            </div>

            <!-- Step 2: Validasi & Pratinjau Diff -->
            <div class="flex items-center gap-3.5 p-3 rounded-xl transition"
                :class="currentStep === 2 ? 'bg-primary-50/80 border border-primary-200/80 shadow-2xs' : (currentStep > 2 ? 'bg-surface-50 border border-surface-100' : 'bg-transparent border border-transparent opacity-60')">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition"
                    :class="currentStep === 2 ? 'bg-primary-600 text-white shadow-sm ring-4 ring-primary-100' : (currentStep > 2 ? 'bg-primary-700 text-white' : 'bg-surface-200 text-surface-600')">
                    <template x-if="currentStep > 2">
                        <i class="fas fa-check" aria-hidden="true"></i>
                    </template>
                    <template x-if="currentStep <= 2">
                        <span>2</span>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition"
                        :class="currentStep === 2 ? 'text-primary-800' : (currentStep > 2 ? 'text-surface-700' : 'text-surface-500')">
                        Tahap 2
                    </p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-surface-900 truncate">Analisis & Pratinjau Perubahan</h4>
                    <p class="text-[11px] text-surface-500 truncate">Verifikasi data baru, penyesuaian, tetap & kendala</p>
                </div>
            </div>

            <!-- Step 3: Konfirmasi & Selesai -->
            <div class="flex items-center gap-3.5 p-3 rounded-xl transition"
                :class="currentStep === 3 ? 'bg-primary-50/80 border border-primary-200/80 shadow-2xs' : 'bg-transparent border border-transparent opacity-60'">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition"
                    :class="currentStep === 3 ? 'bg-primary-600 text-white shadow-sm ring-4 ring-primary-100' : 'bg-surface-200 text-surface-600'">
                    <template x-if="currentStep === 3">
                        <i class="fas fa-check-double" aria-hidden="true"></i>
                    </template>
                    <template x-if="currentStep < 3">
                        <span>3</span>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition"
                        :class="currentStep === 3 ? 'text-primary-800' : 'text-surface-500'">
                        Tahap 3
                    </p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-surface-900 truncate">Konfirmasi & Selesai</h4>
                    <p class="text-[11px] text-surface-500 truncate">Penyimpanan data & ringkasan hasil</p>
                </div>
            </div>
        </div>
    </div>

    {{-- SLIDE-OVER DRAWER: KAMUS FORMAT SPREADSHEET (26 KOLOM) --}}
    @include('admin.employees.partials.import-column-guide-modal')

    {{-- TAHAP 1: UNGGAH BERKAS & TATA KELOLA HR --}}
    @include('admin.employees.partials.import-step1')

    {{-- TAHAP 2: ANALISIS & PRATINJAU PERUBAHAN DIFF --}}
    @include('admin.employees.partials.import-step2')

    {{-- TAHAP 3: RINGKASAN EKSEKUTIF & AKSI OPERASIONAL --}}
    @include('admin.employees.partials.import-step3')

</div>

{{-- LOGIKA KOMPONEN ALPINE.JS & EVENT LISTENERS --}}
@include('admin.employees.partials.import-wizard-script')

@endsection
