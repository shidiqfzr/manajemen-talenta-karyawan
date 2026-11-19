@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-admin.page-header
        title="Edit Penilaian Karyawan"
        subtitle="Perbarui data evaluasi untuk NIK {{ $employee->nik ?? $evaluation->employee_nik }}"
        icon="fas fa-clipboard-check"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Penilaian', 'url' => route('admin.evaluations.index')],
            ['label' => 'Edit Penilaian']
        ]"
        back-url="{{ route('admin.evaluations.index') }}"
        back-label="Kembali ke Daftar Penilaian"
    />

    <x-admin.evaluation-form
        :evaluation="$evaluation"
        :employee="$employee ?? null"
        action="{{ route('admin.evaluations.update', $evaluation->id) }}"
        method="PUT"
    />
</div>
@endsection
