@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <x-admin.module-header title="Manajemen Pelatihan" subtitle="Kelola data pelatihan, jadwal pelaksanaan, dan investasi pengembangan karyawan." :breadcrumbs="['Core HR', 'Manajemen Pelatihan']">
            <x-admin.button href="{{ route('admin.trainings.create') }}" variant="primary" icon="fas fa-plus">Tambah Pelatihan</x-admin.button>
        </x-admin.module-header>

        <x-admin.flash />

        <livewire:admin.training-index-table />
    </div>
@endsection
