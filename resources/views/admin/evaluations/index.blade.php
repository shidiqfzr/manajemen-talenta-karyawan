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

            <!-- Evaluation Table -->
            <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
                <div
                    class="px-6 py-4 bg-gradient-to-r border-b border-surface-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                    <h2 class="text-lg font-semibold text-surface-800 flex items-center">
                        <i class="fa-solid fa-clipboard-list text-warning-700 mr-2" aria-hidden="true"></i>Daftar Penilaian
                    </h2>

                    @if ($evaluations->isNotEmpty())
                        <div class="mt-2 sm:mt-0 text-sm text-surface-500">
                            Menampilkan {{ $evaluations->count() }} dari {{ $evaluations->total() }} data
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <table
                        class="min-w-full bg-white border divide-surface-200 rounded-lg overflow-hidden shadow-md table-auto">
                        <thead class="bg-surface-100 text-sm text-surface-700">
                            <tr>
                                <th scope="col" class="p-3 text-left">NIK</th>
                                <th scope="col" class="p-3 text-left">Nama</th>
                                <th scope="col" class="p-3 text-left">Jabatan</th>
                                <th scope="col" class="p-3 text-left">Unit</th>
                                <th scope="col" class="p-3 text-left">Nilai Tertimbang</th>
                                <th scope="col" class="p-3 text-left">Hasil Skor</th>
                                <th scope="col" class="p-3 text-left">Kategori</th>
                                <th scope="col" class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-surface-800">
                            @forelse($evaluations as $eval)
                                <tr class="border-t hover:bg-surface-50 transition duration-150">
                                    <td class="p-3">{{ $eval->employee_nik  }}</td>
                                    <td class="p-3">{{ $eval->employee->nama ?? '-' }}</td>
                                    <td class="p-3">{{ $eval->employee->jabatan ?? '-' }}</td>
                                    <td class="p-3">{{ $eval->employee->unit_kerja ?? '-' }}</td>
                                    <td class="p-3">{{ number_format($eval->nilai_tertimbang, 2) }}</td>
                                    <td class="p-3">{{ number_format($eval->hasil_skor_asesmen, 2) }}</td>
                                    <td class="p-3">
                                        @php
                                            $categoryColors = [
                                                'Sangat Baik' => 'bg-primary-100 text-primary-800 border-primary-200',
                                                'Baik' => 'bg-info-100 text-info-800 border-info-200',
                                                'Cukup' => 'bg-warning-100 text-warning-800 border-warning-200',
                                                'Kurang' => 'bg-danger-100 text-danger-800 border-danger-200'
                                            ];
                                            $colorClass = $categoryColors[$eval->kategori_asesmen] ?? 'bg-surface-100 text-surface-800 border-surface-200';
                                        @endphp
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border {{ $colorClass }}">
                                            {{ $eval->kategori_asesmen ?? 'Belum Dikategorikan' }}
                                        </span>
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-2 justify-center">
                                            <a href="{{ route('admin.evaluations.show', $eval->id) }}"
                                                class="p-2 bg-info text-white rounded-md hover:bg-info transition"
                                                title="Lihat Detail">
                                                <i class="fas fa-eye" aria-hidden="true"></i>
                                            </a>
                                            <a href="{{ route('admin.evaluations.edit', $eval->id) }}"
                                                class="p-2 bg-warning-700 text-white rounded-md hover:bg-warning-700 transition"
                                                title="Edit Data">
                                                <i class="fas fa-edit" aria-hidden="true"></i>
                                            </a>
                                            <form action="{{ route('admin.evaluations.destroy', $eval->id) }}"
                                                method="POST" onsubmit="return confirm('Hapus penilaian ini?')"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="p-2 bg-danger text-white rounded-md hover:bg-danger transition"
                                                    title="Hapus Penilaian">
                                                    <i class="fas fa-trash" aria-hidden="true"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="bg-surface-100 rounded-full p-3 mb-4">
                                                <i class="fas fa-inbox text-surface-400 text-5xl" aria-hidden="true"></i>
                                            </div>
                                            <h3 class="text-lg font-medium text-surface-900 mb-1">Tidak ada data penilaian</h3>
                                            <p class="text-surface-500 max-w-full text-center mb-4">
                                                Belum ada data penilaian yang tersedia untuk ditampilkan saat ini.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <x-pagination-ui :data="$evaluations" />
            </div>
        </div>
    </div>
@endsection
