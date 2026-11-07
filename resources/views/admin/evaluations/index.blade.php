@extends('layouts.admin')

@section('content')
    <div class="bg-surface-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-surface-800">Manajemen Penilaian</h1>
                    <p class="text-surface-500 mt-1">Kelola data penilaian karyawan</p>
                </div>
                <a href="{{ route('admin.evaluations.create') }}"
                    class="bg-primary-600 text-white px-6 py-2 rounded-md hover:bg-primary-700 transition ease-in-out duration-200 flex items-center">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Penilaian
                </a>
            </div>

            @if (session('success'))
                <div role="status" class="mb-4 p-4 bg-primary-100 text-primary-800 border border-primary-200 rounded-lg shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            <livewire:admin.evaluation-index-table />
        </div>
    </div>
@endsection
