@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-admin.page-header
        title="Tambah Penilaian Karyawan"
        subtitle="Masukkan data evaluasi kinerja, skor 9-Box, dan hasil asesmen"
        icon="fas fa-clipboard-check"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Penilaian', 'url' => route('admin.evaluations.index')],
            ['label' => 'Tambah Penilaian']
        ]"
        back-url="{{ route('admin.evaluations.index') }}"
        back-label="Kembali ke Daftar Penilaian"
    />

    <x-admin.evaluation-form
        :employee="$employee ?? null"
        :employees="$employees ?? []"
        action="{{ route('admin.evaluations.store') }}"
    />
</div>
@endsection
