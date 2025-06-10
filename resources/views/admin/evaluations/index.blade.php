@extends('layouts.admin')

@section('content')
    <div class="bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Manajemen Penilaian</h1>
                    <p class="text-gray-500 mt-1">Kelola data penilaian karyawan</p>
                </div>
                <a href="{{ route('admin.evaluations.create') }}"
                    class="bg-blue-600 text-white px-6 py-2 rounded-md hover:bg-blue-700 transition ease-in-out duration-200 flex items-center">
                    <i class="fas fa-plus mr-2"></i> Tambah Penilaian
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 p-4 bg-green-100 text-green-800 border border-green-200 rounded-lg shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full bg-white border rounded-lg overflow-hidden shadow-md table-auto">
                    <thead class="bg-gray-100 text-sm text-gray-700">
                        <tr>
                            <th class="p-3 text-left">NIK</th>
                            <th class="p-3 text-left">Nama</th>
                            <th class="p-3 text-left">Jabatan</th>
                            <th class="p-3 text-left">Unit</th>
                            <th class="p-3 text-left">Nilai Tertimbang</th>
                            <th class="p-3 text-left">Hasil Skor</th>
                            <th class="p-3 text-left">Kategori</th>
                            <th class="p-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-gray-800">
                        @forelse($evaluations as $eval)
                            <tr class="border-t hover:bg-gray-50 transition duration-150">
                                <td class="p-3">{{ $eval->nik }}</td>
                                <td class="p-3">{{ $eval->employee->nama ?? '-' }}</td>
                                <td class="p-3">{{ $eval->employee->jabatan ?? '-' }}</td>
                                <td class="p-3">{{ $eval->employee->unit_kerja ?? '-' }}</td>
                                <td class="p-3">{{ number_format($eval->nilai_tertimbang, 2) }}</td>
                                <td class="p-3">{{ number_format($eval->hasil_skor_asesmen, 2) }}</td>
                                <td class="p-3">{{ $eval->kategori_asesmen ?? '-' }}</td>
                                <td class="p-3">
                                    <div class="flex items-center gap-2 justify-center">
                                        <a href="{{ route('admin.evaluations.show', $eval->id) }}"
                                            class="p-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition"
                                            title="Lihat Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.evaluations.edit', $eval->id) }}"
                                            class="p-2 bg-yellow-400 text-white rounded-md hover:bg-yellow-500 transition"
                                            title="Edit Data">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('admin.evaluations.destroy', $eval->id) }}"
                                            method="POST" onsubmit="return confirm('Hapus penilaian ini?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                class="p-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition"
                                                title="Hapus Penilaian">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="p-4 text-center text-gray-500">Tidak ada data penilaian.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- <div class="mt-6">
            {{ $evaluations->links('pagination::tailwind') }}
        </div> --}}
        </div>
    </div>
@endsection
