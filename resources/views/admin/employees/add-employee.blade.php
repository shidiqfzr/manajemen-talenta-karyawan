@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-admin.page-header 
        title="Tambah Pegawai Baru"
        subtitle="Input data induk pegawai untuk penempatan baru atau pengisian formasi kosong PTPN IV Regional V"
        icon="fas fa-user-plus"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Direktori Pegawai', 'url' => route('admin.employees.index')],
            ['label' => 'Tambah Pegawai']
        ]"
        back-url="{{ route('admin.employees.index') }}"
        back-label="Kembali ke Direktori"
    />

    @php
        $prefilledJabatan = old('jabatan', $prefill['jabatan'] ?? request('jabatan'));
        $prefilledUnit = old('unit_kerja', $prefill['unit_kerja'] ?? request('unit_kerja'));
    @endphp

    @if ($prefilledJabatan && $prefilledUnit)
        <div class="p-4 bg-emerald-50 border border-emerald-200/90 rounded-2xl shadow-xs flex items-start gap-3.5">
            <div class="w-9 h-9 rounded-xl bg-emerald-600 text-white flex items-center justify-center text-sm shrink-0 mt-0.5 shadow-xs">
                <i class="fas fa-door-closed"></i>
            </div>
            <div class="text-xs text-emerald-950 space-y-1">
                <h4 class="font-extrabold text-emerald-900 text-sm">Rekrutmen Penutup Rantai Formasi Kosong</h4>
                <p class="leading-relaxed">
                    Anda sedang mendaftarkan pegawai baru untuk mengisi lowongan posisi: <strong class="text-slate-900 font-bold">{{ $prefilledJabatan }}</strong> di <strong class="text-slate-900 font-bold">{{ $prefilledUnit }}</strong>.
                </p>
                <p class="text-emerald-700 flex items-center gap-1 font-semibold">
                    <i class="fas fa-check-circle text-xs"></i> Setelah disimpan, lowongan ini otomatis terisi dan tuntas dari <strong>Monitoring Jabatan Kosong</strong>.
                </p>
            </div>
        </div>
    @endif

    <x-admin.employee-form 
        action="{{ route('admin.employees.store') }}" 
        :prefill="$prefill ?? []" 
    />
</div>
@endsection
