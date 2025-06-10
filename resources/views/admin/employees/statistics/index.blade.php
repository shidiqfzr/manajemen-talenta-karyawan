@extends('layouts.admin')

@section('content')
    <div class="bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Statistik Karyawan</h1>
                    <p class="text-gray-500 mt-1">Kelola data statistik karyawan</p>
                </div>
                <a href="{{ route('admin.employees.statistics.create') }}"
                    class="w-full sm:w-auto bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2"></i> Tambah Statistik
                </a>
            </div>

            <!-- Statistic Table -->
            <div class="overflow-x-auto bg-white shadow rounded-lg">
                <table class="min-w-full bg-white border divide-gray-200 rounded-lg overflow-hidden shadow-md table-auto">
                    <thead class="bg-gray-100 text-sm text-gray-700">
                        <tr>
                            <th class="px-6 py-3 text-left">Periode</th>
                            <th class="px-6 py-3 text-left">Karyawan Pimpinan</th>
                            <th class="px-6 py-3 text-left">Karyawan Pelaksana</th>
                            <th class="px-6 py-3 text-left">Total</th>
                            <th class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-gray-700">
                        @forelse ($statistics as $statistic)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    {{ $statistic->periode }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $statistic->jumlah_karpim }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    {{ $statistic->jumlah_karpel }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800 font-semibold">
                                    {{ $statistic->total_karyawan }}</td>
                                <td class="p-3 space-x-2 flex items-center justify-center">
                                    <a href="{{ route('admin.employees.statistics.edit', $statistic->id) }}"
                                        class="bg-yellow-400 text-white px-3 py-1.5 rounded-md hover:bg-yellow-500 transition flex items-center">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </a>
                                    <form action="{{ route('admin.employees.statistics.destroy', $statistic->id) }}"
                                        method="POST" onsubmit="return confirm('Hapus karyawan ini?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="bg-red-500 text-white px-3 py-1.5 rounded-md hover:bg-red-600 transition flex items-center">
                                            <i class="fas fa-trash mr-1"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-6 text-center text-gray-500 text-sm">Belum ada data statistik.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endsection
