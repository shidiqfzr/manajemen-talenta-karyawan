@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header Section -->
    <x-admin.page-header 
        title="Edit Data Pegawai"
        subtitle="Perbarui data induk pegawai untuk {{ $employee->nama }}"
        icon="fas fa-user-pen"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Direktori Pegawai', 'url' => route('admin.employees.index')],
            ['label' => 'Edit Pegawai']
        ]"
        back-url="{{ route('admin.employees.index') }}"
        back-label="Kembali ke Direktori"
    />

    <x-admin.employee-form 
        :employee="$employee"
        method="PUT"
        action="{{ route('admin.employees.update', $employee->nik) }}" 
    />
</div>
@endsection
