@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header
        title="Riwayat & Persetujuan Impor"
        subtitle="Pengajuan, persetujuan, dan pembatalan impor data pegawai. Setiap keputusan tercatat beserta pelaku dan alasannya."
        :breadcrumbs="[
            'Core HR',
            ['label' => 'Direktori Pegawai', 'url' => route('admin.employees.index')],
            ['label' => 'Impor Data Spreadsheet', 'url' => route('admin.employees.import.page')],
            'Riwayat & Persetujuan',
        ]">
        <x-admin.button href="{{ route('admin.employees.import.page') }}" variant="primary" icon="fas fa-file-import">Impor Data Pegawai</x-admin.button>
    </x-admin.module-header>

    <x-admin.flash />

    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 text-sm leading-relaxed {{ $requiresApproval ? 'border-primary-200 bg-primary-50 text-primary-900' : 'border-surface-200 bg-surface-50 text-surface-700' }}" role="status">
        <i class="fas {{ $requiresApproval ? 'fa-user-shield' : 'fa-circle-info' }} mt-1" aria-hidden="true"></i>
        <p>
            @if ($requiresApproval)
                <span class="font-semibold">Persetujuan dua pihak (empat mata) aktif.</span> Impor harus diajukan lalu disetujui admin lain sebelum data pegawai berubah.
                Pengajuan yang tidak diputuskan dalam {{ $approvalTtlDays }} hari kedaluwarsa otomatis.
            @else
                <span class="font-semibold">Persetujuan dua pihak (empat mata) belum diaktifkan.</span> Impor langsung disimpan setelah pratinjau dikonfirmasi.
            @endif
            Impor yang sudah tersimpan dapat dibatalkan dalam {{ $undoWindowDays }} hari, selama pegawai terkait belum berubah.
        </p>
    </div>

    <livewire:admin.employees.import-batch-table />
</div>
@endsection
