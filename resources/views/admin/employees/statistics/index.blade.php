@extends('layouts.admin')

@section('content')
    <div class="bg-surface-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-surface-800">Statistik Karyawan</h1>
                    <p class="text-surface-500 mt-1">Kelola data statistik karyawan</p>
                </div>
                <a href="{{ route('admin.employees.statistics.create') }}"
                    class="w-full sm:w-auto bg-info text-white px-6 py-2.5 rounded-lg hover:bg-info-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Statistik
                </a>
            </div>

            <!-- Statistic Table -->
            <div class="overflow-x-auto bg-white shadow rounded-lg">
                <table class="min-w-full bg-white border divide-surface-200 rounded-lg overflow-hidden shadow-md table-auto">
                    <thead class="bg-surface-100 text-sm text-surface-700">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left">Periode</th>
                            <th scope="col" class="px-6 py-3 text-left">Karyawan Pimpinan</th>
                            <th scope="col" class="px-6 py-3 text-left">Karyawan Pelaksana</th>
                            <th scope="col" class="px-6 py-3 text-left">Total</th>
                            <th scope="col" class="px-6 py-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm text-surface-700">
                        @forelse ($statistics as $statistic)
                            <tr class="hover:bg-surface-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-surface-900">
                                    {{ $statistic->periode }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-surface-700">
                                    {{ $statistic->jumlah_karpim }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-surface-700">
                                    {{ $statistic->jumlah_karpel }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-surface-800 font-semibold">
                                    {{ $statistic->total_karyawan }}</td>
                                <td class="p-3 space-x-2 flex items-center justify-center">
                                    <a href="{{ route('admin.employees.statistics.edit', $statistic->id) }}"
                                        class="bg-warning-700 text-white px-3 py-1.5 rounded-md hover:bg-warning-700 transition flex items-center">
                                        <i class="fas fa-edit mr-1" aria-hidden="true"></i> Edit
                                    </a>
                                    <form action="{{ route('admin.employees.statistics.destroy', $statistic->id) }}"
                                        method="POST" onsubmit="return confirm('Hapus karyawan ini?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                            class="bg-danger text-white px-3 py-1.5 rounded-md hover:bg-danger transition flex items-center">
                                            <i class="fas fa-trash mr-1" aria-hidden="true"></i> Hapus
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-6 text-center text-surface-500 text-sm">Belum ada data statistik.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endsection
