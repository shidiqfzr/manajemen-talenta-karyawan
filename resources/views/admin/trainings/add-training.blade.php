@extends('layouts.admin')

@section('content')
<div class="space-y-6 max-w-5xl mx-auto">
    <x-admin.page-header
        title="Tambah Data Pelatihan"
        subtitle="Masukkan detail program pelatihan, jadwal, biaya, dan surat tugas"
        icon="fas fa-chalkboard-teacher"
        :breadcrumbs="[
            ['label' => 'Core HR'],
            ['label' => 'Pelatihan', 'url' => route('admin.trainings.index')],
            ['label' => 'Tambah Pelatihan']
        ]"
        back-url="{{ route('admin.trainings.index') }}"
        back-label="Kembali ke Daftar Pelatihan"
    />

    <x-admin.training-form action="{{ route('admin.trainings.store') }}" />
</div>
@endsection
