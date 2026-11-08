@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <!-- Header Section -->
    <x-admin.page-header 
        title="Edit Data Karyawan"
        subtitle="Perbarui data induk karyawan untuk {{ $employee->nama }}"
        icon="fas fa-user-pen"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Daftar Karyawan', 'url' => route('admin.employees.index')],
            ['label' => 'Edit Karyawan']
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
