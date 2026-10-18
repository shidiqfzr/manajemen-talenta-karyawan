@extends('layouts.admin')

@section('content')
<div x-data="employeeImportWizard()" class="space-y-6 max-w-7xl mx-auto pb-12">
    {{-- MODAL PROSES LATAR BELAKANG & INSPEKSI PERUBAHAN --}}
    @include('admin.employees.partials.import-async-modal')
    @include('admin.employees.partials.import-diff-modal')

    <!-- Header Standar Enterprise -->
    <x-admin.page-header 
        title="Impor Data Pegawai"
        subtitle="Alur unggah massal data induk pegawai dengan validasi multi-tahap, pratinjau analisis perubahan, dan mitigasi kesalahan."
        icon="fas fa-file-excel"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Direktori Pegawai', 'url' => route('admin.employees.index')],
            ['label' => 'Impor Data Spreadsheet']
        ]"
        back-url="{{ route('admin.employees.index') }}"
        back-label="Kembali ke Direktori"
    />

    <!-- ============================================================ -->
    <!-- ENTERPRISE STEPPER PROGRESS BAR (DESIGN.md Bab 6.6)          -->
    <!-- ============================================================ -->
    <div class="bg-white rounded-2xl border border-slate-200/80 p-4 sm:p-5 shadow-xs">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 relative">
            <!-- Step 1: Upload & Format Kolom -->
            <div class="flex items-center gap-3.5 p-3 rounded-xl transition"
                :class="currentStep === 1 ? 'bg-emerald-50/80 border border-emerald-200/80 shadow-2xs' : (currentStep > 1 ? 'bg-slate-50 border border-slate-100' : 'bg-transparent border border-transparent opacity-60')">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition"
                    :class="currentStep === 1 ? 'bg-emerald-600 text-white shadow-sm ring-4 ring-emerald-100' : (currentStep > 1 ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-600')">
                    <template x-if="currentStep > 1">
                        <i class="fas fa-check"></i>
                    </template>
                    <template x-if="currentStep <= 1">
                        <span>1</span>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition"
                        :class="currentStep === 1 ? 'text-emerald-800' : (currentStep > 1 ? 'text-slate-700' : 'text-slate-400')">
                        Tahap 1
                    </p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate">Unggah & Panduan Kolom</h4>
                    <p class="text-[11px] text-slate-500 truncate">Pilih spreadsheet & format 26 kolom</p>
                </div>
            </div>

            <!-- Step 2: Validasi & Pratinjau Diff -->
            <div class="flex items-center gap-3.5 p-3 rounded-xl transition"
                :class="currentStep === 2 ? 'bg-emerald-50/80 border border-emerald-200/80 shadow-2xs' : (currentStep > 2 ? 'bg-slate-50 border border-slate-100' : 'bg-transparent border border-transparent opacity-60')">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition"
                    :class="currentStep === 2 ? 'bg-emerald-600 text-white shadow-sm ring-4 ring-emerald-100' : (currentStep > 2 ? 'bg-emerald-700 text-white' : 'bg-slate-200 text-slate-600')">
                    <template x-if="currentStep > 2">
                        <i class="fas fa-check"></i>
                    </template>
                    <template x-if="currentStep <= 2">
                        <span>2</span>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition"
                        :class="currentStep === 2 ? 'text-emerald-800' : (currentStep > 2 ? 'text-slate-700' : 'text-slate-400')">
                        Tahap 2
                    </p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate">Analisis & Pratinjau Perubahan</h4>
                    <p class="text-[11px] text-slate-500 truncate">Verifikasi data baru, penyesuaian, tetap & kendala</p>
                </div>
            </div>

            <!-- Step 3: Konfirmasi & Selesai -->
            <div class="flex items-center gap-3.5 p-3 rounded-xl transition"
                :class="currentStep === 3 ? 'bg-emerald-50/80 border border-emerald-200/80 shadow-2xs' : 'bg-transparent border border-transparent opacity-60'">
                <div class="w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold shrink-0 transition"
                    :class="currentStep === 3 ? 'bg-emerald-600 text-white shadow-sm ring-4 ring-emerald-100' : 'bg-slate-200 text-slate-600'">
                    <template x-if="currentStep === 3">
                        <i class="fas fa-check-double"></i>
                    </template>
                    <template x-if="currentStep < 3">
                        <span>3</span>
                    </template>
                </div>
                <div class="min-w-0">
                    <p class="text-[11px] font-bold uppercase tracking-wider transition"
                        :class="currentStep === 3 ? 'text-emerald-800' : 'text-slate-400'">
                        Tahap 3
                    </p>
                    <h4 class="text-xs sm:text-sm font-extrabold text-slate-900 truncate">Konfirmasi & Selesai</h4>
                    <p class="text-[11px] text-slate-500 truncate">Penyimpanan data & ringkasan hasil</p>
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
