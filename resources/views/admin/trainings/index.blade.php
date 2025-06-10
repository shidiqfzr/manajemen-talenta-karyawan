@extends('layouts.admin')

@section('content')
    <div class="bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">Manajemen Pelatihan</h1>
                    <p class="text-gray-500 mt-1">Kelola data pelatihan karyawan</p>
                </div>
                <a href="{{ route('admin.trainings.create') }}"
                    class="w-full sm:w-auto bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2"></i> Tambah Pelatihan
                </a>
            </div>

            <!-- Main Content -->
            <div class="space-y-6">

                <!-- Statistics Cards -->
                @if (isset($totalManHours))
                    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-green-50 to-white border-b border-gray-200">
                            <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                                <i class="fas fa-chart-pie text-green-500 mr-2"></i> Ringkasan Statistik
                                @if (request('start_date') || request('end_date'))
                                    <span class="ml-2 text-sm font-normal text-gray-500">
                                        ({{ request('start_date') ? \Carbon\Carbon::parse(request('start_date'))->format('d M Y') : 'Awal' }}
                                        -
                                        {{ request('end_date') ? \Carbon\Carbon::parse(request('end_date'))->format('d M Y') : 'Sekarang' }})
                                    </span>
                                @endif
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                                <!-- Man Hours Card -->
                                <div
                                    class="bg-gradient-to-br from-green-50 to-green-100 border border-green-200 rounded-xl p-6 flex flex-col relative overflow-hidden shadow-sm transform transition hover:scale-105">
                                    <div class="absolute top-0 right-0 mt-4 mr-4 text-green-200 opacity-50">
                                        <i class="fas fa-clock fa-3x"></i>
                                    </div>
                                    <span class="text-sm font-medium text-green-600 uppercase tracking-wider">Total Man
                                        Hours</span>
                                    <span
                                        class="text-3xl font-bold text-green-700 mt-2">{{ number_format($totalManHours, 0, ',', '.') }}</span>
                                    <div class="flex items-center mt-4 text-xs text-green-600">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        <span>Total jam pelatihan seluruh peserta</span>
                                    </div>
                                </div>

                                <!-- Ticket Card -->
                                @if (isset($totalTiketPeserta))
                                    <div
                                        class="bg-gradient-to-br from-blue-50 to-blue-100 border border-blue-200 rounded-xl p-6 flex flex-col relative overflow-hidden shadow-sm transform transition hover:scale-105">
                                        <div class="absolute top-0 right-0 mt-4 mr-4 text-blue-200 opacity-50">
                                            <i class="fas fa-ticket-alt fa-3x"></i>
                                        </div>
                                        <span class="text-sm font-medium text-blue-600 uppercase tracking-wider">Total Tiket
                                            Peserta</span>
                                        <span class="text-3xl font-bold text-blue-700 mt-2">Rp
                                            {{ number_format($totalTiketPeserta, 0, ',', '.') }}</span>
                                        <div class="flex items-center mt-4 text-xs text-blue-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <span>Total tiket peserta pelatihan</span>
                                        </div>
                                    </div>
                                @endif

                                <!-- Cost Card -->
                                @if (isset($totalBiayaPelatihan))
                                    <div
                                        class="bg-gradient-to-br from-indigo-50 to-indigo-100 border border-indigo-200 rounded-xl p-6 flex flex-col relative overflow-hidden shadow-sm transform transition hover:scale-105">
                                        <div class="absolute top-0 right-0 mt-4 mr-4 text-indigo-200 opacity-50">
                                            <i class="fas fa-money-bill-wave fa-3x"></i>
                                        </div>
                                        <span class="text-sm font-medium text-indigo-600 uppercase tracking-wider">Total
                                            Biaya</span>
                                        <span class="text-3xl font-bold text-indigo-700 mt-2">Rp
                                            {{ number_format($totalBiayaPelatihan, 0, ',', '.') }}</span>
                                        <div class="flex items-center mt-4 text-xs text-indigo-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <span>Total investasi pelatihan</span>
                                        </div>
                                    </div>
                                @endif

                                <!-- Employees Card -->
                                @if (isset($totalKaryawan) && $totalKaryawan > 0)
                                    <div
                                        class="bg-gradient-to-br from-yellow-50 to-yellow-100 border border-yellow-200 rounded-xl p-6 flex flex-col relative overflow-hidden shadow-sm transform transition hover:scale-105">
                                        <div class="absolute top-0 right-0 mt-4 mr-4 text-yellow-200 opacity-50">
                                            <i class="fas fa-users fa-3x"></i>
                                        </div>
                                        <span class="text-sm font-medium text-yellow-600 uppercase tracking-wider">Total
                                            Karyawan</span>
                                        <span
                                            class="text-3xl font-bold text-yellow-700 mt-2">{{ number_format($totalKaryawan, 0, ',', '.') }}</span>
                                        <div class="flex items-center mt-4 text-xs text-yellow-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <span>Jumlah karyawan aktif</span>
                                        </div>
                                    </div>

                                    <!-- Average Hours Card -->
                                    <div
                                        class="bg-gradient-to-br from-amber-50 to-amber-100 border border-amber-200 rounded-xl p-6 flex flex-col relative overflow-hidden shadow-sm transform transition hover:scale-105">
                                        <div class="absolute top-0 right-0 mt-4 mr-4 text-amber-200 opacity-50">
                                            <i class="fas fa-chart-line fa-3x"></i>
                                        </div>
                                        <span class="text-sm font-medium text-amber-600 uppercase tracking-wider">Rata-rata
                                            JPL/Karyawan</span>
                                        <span
                                            class="text-3xl font-bold text-amber-700 mt-2">{{ number_format($totalManHours / $totalKaryawan, 2, ',', '.') }}</span>
                                        <div class="flex items-center mt-4 text-xs text-amber-600">
                                            <i class="fas fa-info-circle mr-1"></i>
                                            <span>Jam pelatihan per karyawan</span>
                                        </div>
                                    </div>
                                @else
                                    <!-- Warning Card -->
                                    <div
                                        class="bg-red-50 border border-red-200 rounded-xl p-6 flex items-center col-span-full">
                                        <div class="text-red-500 mr-4">
                                            <i class="fas fa-exclamation-triangle text-2xl"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-medium text-red-700">Data Tidak Lengkap</h3>
                                            <p class="text-red-600 mt-1">Data total karyawan belum tersedia untuk periode
                                                ini.</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Filter Card -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-blue-50 to-white border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fas fa-filter text-blue-500 mr-2"></i> Filter Data Pelatihan
                        </h2>
                    </div>

                    <div class="p-6">
                        <form method="GET" class="grid grid-cols-1 md:grid-cols-12 gap-6">
                            <div class="md:col-span-3">
                                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Dari
                                    Tanggal</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-calendar-alt text-gray-400"></i>
                                    </div>
                                    <input type="date" name="start_date" id="start_date"
                                        value="{{ request('start_date') }}"
                                        class="w-full pl-10 border-gray-300 rounded-lg shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="md:col-span-3">
                                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">Sampai
                                    Tanggal</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                        <i class="fas fa-calendar-alt text-gray-400"></i>
                                    </div>
                                    <input type="date" name="end_date" id="end_date" value="{{ request('end_date') }}"
                                        class="w-full pl-10 border-gray-300 rounded-lg shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="md:col-span-4 flex items-end space-x-2">
                                <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2 rounded-lg text-sm transition flex-1 md:flex-none flex justify-center items-center">
                                    <i class="fas fa-search mr-2"></i> Terapkan Filter
                                </button>
                                @if (request('start_date') || request('end_date'))
                                    <a href="{{ route('admin.trainings.index') }}"
                                        class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-lg text-sm transition flex justify-center items-center">
                                        <i class="fas fa-undo mr-2"></i> Reset
                                    </a>
                                @endif
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Training Table -->
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                    <div
                        class="px-6 py-4 bg-gradient-to-r from-yellow-50 to-white border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                        <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="fa-solid fa-clipboard-list text-yellow-500 mr-2"></i>Daftar Pelatihan
                        </h2>
                        <!-- Only show count if have trainings -->
                        @if ($trainings->isNotEmpty())
                            <div class="mt-2 sm:mt-0 text-sm text-gray-500">
                                @if (method_exists($trainings, 'total') && method_exists($trainings, 'count'))
                                    Menampilkan {{ $trainings->count() }} dari {{ $trainings->total() }} data
                                @else
                                    Menampilkan {{ $trainings->count() }} data
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="overflow-x-auto bg-white shadow rounded-lg">
                        <table
                            class="min-w-full bg-white border divide-gray-200 rounded-lg overflow-hidden shadow-md table-auto">
                            <thead class="bg-gray-100 text-sm text-gray-700">
                                <tr>
                                    <th class="p-3 text-left">Judul</th>
                                    <th class="p-3 text-left">Jenis</th>
                                    <th class="p-3 text-left">Metode</th>
                                    <th class="p-3 text-left">Bidang</th>
                                    <th class="p-3 text-left">Tanggal Mulai</th>
                                    <th class="p-3 text-left">Man Hours</th>
                                    <th class="p-3 text-left">Peserta</th>
                                    <th class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-700">
                                @forelse ($trainings as $training)
                                    <tr class="border-t hover:bg-gray-50 transition duration-150">
                                        <td class="p-3">{{ $training->judul }}</td>
                                        <td class="p-3">{{ $training->jenis }}</td>
                                        <td class="p-3">{{ $training->metode }}</td>
                                        <td class="p-3">{{ $training->bidang_pelatihan }}</td>
                                        <td class="p-3">
                                            {{ \Carbon\Carbon::parse($training->tanggal_mulai)->format('d M Y') }}</td>
                                        <td class="p-3">{{ $training->jumlah_man_hours }}</td>
                                        <td class="p-3">{{ $training->employees_count ?? 0 }}</td>
                                        <td class="p-3">
                                            <div class="flex items-center gap-2 justify-center">
                                                <a href="{{ route('admin.trainings.show', $training->id) }}"
                                                    class="p-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 transition"
                                                    title="Lihat Detail">
                                                    <i class="fas fa-eye"></i>
                                                </a>

                                                <a href="{{ route('admin.trainings.edit', $training->id) }}"
                                                    class="p-2 bg-yellow-400 text-white rounded-md hover:bg-yellow-500 transition"
                                                    title="Edit Data">
                                                    <i class="fas fa-edit"></i>
                                                </a>

                                                <form action="{{ route('admin.trainings.destroy', $training->id) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelatihan ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="p-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition"
                                                        title="Hapus Pelatihan">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="px-6 py-12 text-center">
                                            <div class="flex flex-col items-center">
                                                <div class="bg-gray-100 rounded-full p-3 mb-4">
                                                    <svg xmlns="http://www.w3.org/2000/svg"
                                                        class="h-12 w-12 text-gray-400" fill="none"
                                                        viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="1.5"
                                                            d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4" />
                                                    </svg>
                                                </div>
                                                <h3 class="text-lg font-medium text-gray-900 mb-1">Tidak ada data pelatihan
                                                </h3>
                                                <p class="text-gray-500 max-w-sm text-center mb-4">
                                                    Belum ada data pelatihan yang tersedia untuk ditampilkan saat ini.
                                                </p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>


                    <!-- Pagination -->
                    @if ($trainings->isNotEmpty() && method_exists($trainings, 'links'))
                        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                            {{ $trainings->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
