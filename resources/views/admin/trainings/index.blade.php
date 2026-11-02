@extends('layouts.admin')

@section('content')
    <div class="bg-surface-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-surface-800">Manajemen Pelatihan</h1>
                    <p class="text-surface-500 mt-1">Kelola data pelatihan karyawan</p>
                </div>
                <a href="{{ route('admin.trainings.create') }}"
                    class="w-full sm:w-auto bg-info text-white px-6 py-2.5 rounded-lg hover:bg-info-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Pelatihan
                </a>
            </div>

            <!-- Main Content -->
            <div class="space-y-6">

                <!-- Statistics Cards -->
                @if (isset($totalManHours))
                    <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
                        <div class="px-6 py-4 bg-gradient-to-r from-primary-50 to-white border-b border-surface-200">
                            <h2 class="text-lg font-semibold text-surface-800 flex items-center">
                                <i class="fas fa-chart-pie text-primary-500 mr-2" aria-hidden="true"></i> Ringkasan Statistik
                                @if (request('start_date') || request('end_date'))
                                    <span class="ml-2 text-sm font-normal text-surface-500">
                                        ({{ request('start_date') ? \Carbon\Carbon::parse(request('start_date'))->format('d M Y') : 'Awal' }}
                                        -
                                        {{ request('end_date') ? \Carbon\Carbon::parse(request('end_date'))->format('d M Y') : 'Sekarang' }})
                                    </span>
                                @endif
                            </h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
                                <x-admin.kpi-card
                                    title="Total Man Hours"
                                    :value="number_format($totalManHours, 0, ',', '.')"
                                    subtitle="Total jam pelatihan seluruh peserta"
                                    icon="fas fa-clock"
                                    theme="primary"
                                    :coloredValue="false"
                                />

                                @if (isset($totalTiketPeserta))
                                    <x-admin.kpi-card
                                        title="Total Tiket Peserta"
                                        value="Rp {{ number_format($totalTiketPeserta, 0, ',', '.') }}"
                                        subtitle="Total tiket peserta pelatihan"
                                        icon="fas fa-ticket-alt"
                                        theme="info"
                                        :coloredValue="false"
                                    />
                                @endif

                                @if (isset($totalBiayaPelatihan))
                                    <x-admin.kpi-card
                                        title="Total Biaya Investasi"
                                        value="Rp {{ number_format($totalBiayaPelatihan, 0, ',', '.') }}"
                                        subtitle="Total anggaran pelatihan"
                                        icon="fas fa-money-bill-wave"
                                        theme="neutral"
                                        :coloredValue="false"
                                    />
                                @endif

                                @if (isset($totalKaryawan) && $totalKaryawan > 0)
                                    <x-admin.kpi-card
                                        title="Total Karyawan"
                                        :value="number_format($totalKaryawan, 0, ',', '.')"
                                        subtitle="Jumlah karyawan aktif"
                                        icon="fas fa-users"
                                        theme="teal"
                                        :coloredValue="false"
                                    />

                                    <x-admin.kpi-card
                                        title="Rata-rata JPL / Karyawan"
                                        :value="number_format($totalManHours / $totalKaryawan, 2, ',', '.')"
                                        subtitle="Jam pelatihan per karyawan"
                                        icon="fas fa-chart-line"
                                        theme="earth"
                                        :coloredValue="true"
                                    />
                                @else
                                    <div class="bg-danger-50/80 border border-danger-200 rounded-2xl p-4 flex items-center col-span-full">
                                        <div class="w-10 h-10 rounded-xl bg-danger-100 text-danger flex items-center justify-center text-lg mr-3 shrink-0">
                                            <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                                        </div>
                                        <div>
                                            <h3 class="font-bold text-danger-800 text-sm">Data Belum Lengkap</h3>
                                            <p class="text-xs text-danger mt-0.5">Data total karyawan belum tersedia untuk periode ini.</p>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Filter Card -->
                <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 bg-gradient-to-r from-primary-50 to-white border-b border-surface-200">
                        <h2 class="text-lg font-semibold text-surface-800 flex items-center">
                            <i class="fas fa-filter text-primary-500 mr-2" aria-hidden="true"></i> Filter & Export Data
                        </h2>
                    </div>
                    <div class="p-6">
                        <form method="GET" action="{{ route('admin.trainings.index') }}" id="filterForm">
                            <!-- Inline Filter Row -->
                            <div class="flex flex-col lg:flex-row gap-4 items-end">
                                <!-- Start Date -->
                                <div class="flex-1 min-w-0">
                                    <label for="start_date" class="block text-sm font-medium text-surface-700 mb-1">
                                        <i class="fas fa-calendar-day text-surface-400 mr-1" aria-hidden="true"></i>
                                        Dari Tanggal
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <i class="fas fa-calendar-alt text-surface-400 text-sm" aria-hidden="true"></i>
                                        </div>
                                        <input type="date" name="start_date" id="start_date"
                                            value="{{ request('start_date') }}"
                                            class="w-full pl-10 pr-3 py-2.5 border border-surface-300 rounded-lg shadow-sm text-sm 
                                      focus:ring-2 focus:ring-info focus:border-info 
                                      hover:border-surface-400 transition-colors duration-200
                                      bg-white placeholder-surface-400">
                                    </div>
                                </div>

                                <!-- End Date -->
                                <div class="flex-1 min-w-0">
                                    <label for="end_date" class="block text-sm font-medium text-surface-700 mb-1">
                                        <i class="fas fa-calendar-week text-surface-400 mr-1" aria-hidden="true"></i>
                                        Sampai Tanggal
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none">
                                            <i class="fas fa-calendar-alt text-surface-400 text-sm" aria-hidden="true"></i>
                                        </div>
                                        <input type="date" name="end_date" id="end_date"
                                            value="{{ request('end_date') }}"
                                            class="w-full pl-10 pr-3 py-2.5 border border-surface-300 rounded-lg shadow-sm text-sm 
                                      focus:ring-2 focus:ring-info focus:border-info 
                                      hover:border-surface-400 transition-colors duration-200
                                      bg-white placeholder-surface-400">
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">
                                    <button type="submit"
                                        class="bg-info hover:bg-info-700 text-white px-4 py-3 rounded-lg text-sm font-medium
                                   transition-all duration-200 flex justify-center items-center
                                   shadow-sm hover:shadow-md whitespace-nowrap">
                                        <i class="fas fa-search mr-2" aria-hidden="true"></i>
                                        <span>Filter</span>
                                    </button>

                                    <button type="submit" formaction="{{ route('admin.trainings.export') }}"
                                        class="bg-primary-600 hover:bg-primary-700 text-white px-4 py-3 rounded-lg text-sm font-medium
                                   transition-all duration-200 flex justify-center items-center
                                   shadow-sm hover:shadow-md whitespace-nowrap">
                                        <i class="fas fa-file-excel mr-2" aria-hidden="true"></i>
                                        <span>Export</span>
                                    </button>

                                    @if (request('start_date') || request('end_date'))
                                        <a href="{{ route('admin.trainings.index') }}"
                                            class="bg-surface-100 hover:bg-surface-200 text-surface-700 px-4 py-3 rounded-lg text-sm font-medium
                                  transition-all duration-200 flex justify-center items-center
                                  border border-surface-300 hover:border-surface-400 whitespace-nowrap">
                                            <i class="fas fa-undo mr-2" aria-hidden="true"></i>
                                            <span>Reset Filter</span>
                                        </a>
                                    @endif
                                </div>
                            </div>

                            <!-- Current Filter Display -->
                            @if (request('start_date') || request('end_date'))
                                <div class="mt-4 p-3 bg-info-50 border border-info-200 rounded-lg">
                                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                        <div class="flex items-center text-sm text-info-700">
                                            <i class="fas fa-info-circle mr-2" aria-hidden="true"></i>
                                            <span class="font-medium">Filter aktif:</span>
                                            <span class="ml-2">
                                                {{ request('start_date') ? \Carbon\Carbon::parse(request('start_date'))->format('d M Y') : 'Semua waktu' }}
                                                -
                                                {{ request('end_date') ? \Carbon\Carbon::parse(request('end_date'))->format('d M Y') : 'Sekarang' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </form>
                    </div>
                </div>

                <!-- Training Table -->
                <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
                    <div
                        class="px-6 py-4 bg-gradient-to-r from-primary-50 to-white border-b border-surface-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                        <h2 class="text-lg font-semibold text-surface-800 flex items-center">
                            <i class="fa-solid fa-clipboard-list text-primary-500 mr-2" aria-hidden="true"></i>Daftar Pelatihan
                        </h2>
                        <!-- Only show count if have trainings -->
                        @if ($trainings->isNotEmpty())
                            <div class="mt-2 sm:mt-0 text-sm text-surface-500">
                                Menampilkan {{ $trainings->count() }} dari {{ $trainings->total() }} data
                            </div>
                        @endif
                    </div>
                    <div class="overflow-x-auto bg-white shadow rounded-lg">
                        <table
                            class="min-w-full bg-white border divide-surface-200 rounded-lg overflow-hidden shadow-md table-auto">
                            <thead class="bg-surface-100 text-sm text-surface-700">
                                <tr>
                                    <th scope="col" class="p-3 text-left">Judul</th>
                                    <th scope="col" class="p-3 text-left">Jenis</th>
                                    <th scope="col" class="p-3 text-left">Metode</th>
                                    <th scope="col" class="p-3 text-left">Bidang</th>
                                    <th scope="col" class="p-3 text-left">Tanggal Mulai</th>
                                    <th scope="col" class="p-3 text-left">Man Hours</th>
                                    <th scope="col" class="p-3 text-left">Peserta</th>
                                    <th scope="col" class="p-3 text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-surface-700">
                                @forelse ($trainings as $training)
                                    <tr class="border-t hover:bg-surface-50 transition duration-150">
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
                                                    class="p-2 bg-info text-white rounded-md hover:bg-info transition"
                                                    title="Lihat Detail">
                                                    <i class="fas fa-eye" aria-hidden="true"></i>
                                                </a>

                                                <a href="{{ route('admin.trainings.edit', $training->id) }}"
                                                    class="p-2 bg-warning-700 text-white rounded-md hover:bg-warning-700 transition"
                                                    title="Edit Data">
                                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                                </a>

                                                <form action="{{ route('admin.trainings.destroy', $training->id) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Apakah Anda yakin ingin menghapus pelatihan ini?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="p-2 bg-danger text-white rounded-md hover:bg-danger transition"
                                                        title="Hapus Pelatihan">
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
                                                <h3 class="text-lg font-medium text-surface-900 mb-1">Tidak ada data pelatihan
                                                </h3>
                                                <p class="text-surface-500 max-w-full text-center mb-4">
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
                    <x-pagination-ui :data="$trainings" />
                </div>
            </div>
        </div>
    </div>
@endsection
