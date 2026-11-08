@extends('layouts.admin')

@section('content')
    <div class="space-y-6">
        <x-admin.module-header title="Manajemen Penilaian" subtitle="Kelola data penilaian kinerja dan potensi karyawan untuk matriks talenta 9-Box." :breadcrumbs="['Core HR', 'Manajemen Penilaian']">
            <x-admin.button href="{{ route('admin.evaluations.create') }}" variant="primary" icon="fas fa-plus">Tambah Penilaian</x-admin.button>
        </x-admin.module-header>

        <x-admin.flash />

        <livewire:admin.evaluation-index-table />
    </div>
@endsection
