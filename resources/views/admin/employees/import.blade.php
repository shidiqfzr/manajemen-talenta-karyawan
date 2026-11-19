@extends('layouts.admin')

@section('content')
@php
    // Jumlah kolom mengikuti kamus kolom (satu sumber), bukan angka tetap pada teks.
    $guideTotal = collect($dictionaryCategories)->flatMap(fn ($category) => $category['columns'])->count();
@endphp
<div x-data="employeeImportWizard(@js($wizard))" @open-import-diff.window="openDiffModal($event.detail)" class="space-y-6 max-w-7xl mx-auto pb-12">
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
            ['label' => 'Karyawan', 'url' => route('admin.employees.index')],
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

    {{-- Penanda tahap bersama (DESIGN.md §6.1, §7.1): komponen yang sama dengan Catat & Terapkan SK Struktur. --}}
    <x-admin.stepper current-expression="currentStep" label="Tahap impor data karyawan" :steps="[
        ['title' => 'Unggah & Panduan Kolom', 'description' => 'Pilih spreadsheet & format '.$guideTotal.' kolom'],
        ['title' => 'Analisis & Pratinjau Perubahan', 'description' => 'Verifikasi data baru, penyesuaian, tetap & kendala'],
        ['title' => 'Konfirmasi & Selesai', 'description' => 'Penyimpanan data & ringkasan hasil'],
    ]" />

    {{-- SLIDE-OVER DRAWER: KAMUS FORMAT SPREADSHEET (26 KOLOM) --}}
    @include('admin.employees.partials.import-column-guide-modal')

    {{-- TAHAP 1: UNGGAH BERKAS & TATA KELOLA HR --}}
    @include('admin.employees.partials.import-step1')

    {{-- TAHAP 2: ANALISIS & PRATINJAU PERUBAHAN DIFF --}}
    @include('admin.employees.partials.import-step2')

    {{-- TAHAP 3: RINGKASAN EKSEKUTIF & AKSI OPERASIONAL --}}
    @include('admin.employees.partials.import-step3')

</div>

@endsection
