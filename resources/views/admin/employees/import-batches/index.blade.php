@extends('layouts.admin')

@section('content')
<div class="space-y-6">
    <x-admin.module-header
        title="Riwayat & Persetujuan Impor"
        subtitle="Pengajuan, persetujuan, dan pembatalan impor data karyawan. Setiap keputusan tercatat beserta pelaku dan alasannya."
        :breadcrumbs="[
            'Core HR',
            ['label' => 'Karyawan', 'url' => route('admin.employees.index')],
            ['label' => 'Impor Data Spreadsheet', 'url' => route('admin.employees.import.page')],
            'Riwayat & Persetujuan',
        ]">
        <x-admin.button href="{{ route('admin.employees.import.page') }}" variant="primary" icon="fas fa-file-import">Impor Data Karyawan</x-admin.button>
    </x-admin.module-header>

    <x-admin.flash />

    <x-admin.approval-policy-notice :active="$requiresApproval">
        <x-slot:enabled>Impor harus diajukan lalu disetujui admin lain sebelum data karyawan berubah. Pengajuan yang tidak diputuskan dalam {{ $approvalTtlDays }} hari kedaluwarsa otomatis.</x-slot:enabled>
        <x-slot:disabled>Impor langsung disimpan setelah pratinjau dikonfirmasi.</x-slot:disabled>
        Impor yang sudah tersimpan dapat dibatalkan dalam {{ $undoWindowDays }} hari, selama karyawan terkait belum berubah.
    </x-admin.approval-policy-notice>

    <livewire:admin.employees.import-batch-table />
</div>
@endsection
