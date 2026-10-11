@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">

    <!-- Page Header Card -->
    <x-admin.page-header 
        title="Buat Mutasi & Penempatan Baru"
        subtitle="Penugasan karyawan ke formasi kosong, rotasi berkala, atau promosi struktural"
        icon="fas fa-paper-plane"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Mutasi & Penempatan', 'url' => route('admin.mutasi.index')],
            ['label' => 'Buat Mutasi Baru']
        ]"
        back-url="{{ route('admin.mutasi.index') }}"
        back-label="Kembali ke Hub Mutasi"
    />

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
        @livewire('admin.mutasi-process', ['jabatan' => request('jabatan'), 'unit' => request('unit'), 'nik' => request('nik')])
    </div>

</div>
@endsection
