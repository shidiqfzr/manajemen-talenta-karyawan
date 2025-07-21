@extends('layouts.admin')

@section('content')
    <div class="bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-800">{{ $training->judul }}</h1>
                    <p class="text-gray-500 mt-1">Detail informasi dari pelatihan</p>
                </div>
                <a href="{{ route('admin.trainings.participants.create', $training->id) }}"
                    class="w-full sm:w-auto bg-blue-600 text-white px-6 py-2.5 rounded-lg hover:bg-blue-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2"></i> Tambah Peserta
                </a>
            </div>

            <!-- Detail Info -->
            <div
                class="bg-gradient-to-br from-white to-gray-50 border border-gray-200 rounded-2xl shadow-lg mb-8 overflow-hidden">
                <!-- Header Section -->
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 rounded-full p-2.5">
                            <i class="fas fa-graduation-cap text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white">Informasi Pelatihan</h2>
                            <p class="text-blue-100 text-sm mt-1">Detail lengkap mengenai pelatihan ini</p>
                        </div>
                    </div>
                </div>

                <!-- Content Section -->
                <div class="p-6">
                    <!-- Training Overview Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <!-- Training Type Card -->
                        <div
                            class="bg-blue-50 border border-blue-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-blue-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-tag text-blue-600 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Jenis</p>
                                    <p class="text-sm font-semibold text-gray-900 truncate" title="{{ $training->jenis }}">
                                        {{ $training->jenis }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Method Card -->
                        <div
                            class="bg-green-50 border border-green-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-green-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-chalkboard-teacher text-green-600 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-green-600 uppercase tracking-wide">Metode</p>
                                    <p class="text-sm font-semibold text-gray-900 truncate" title="{{ $training->metode }}">
                                        {{ $training->metode }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Field Card -->
                        <div
                            class="bg-purple-50 border border-purple-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-purple-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-book text-purple-600 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-purple-600 uppercase tracking-wide">Bidang</p>
                                    <p class="text-sm font-semibold text-gray-900 truncate"
                                        title="{{ $training->bidang_pelatihan }}">{{ $training->bidang_pelatihan }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Duration Card -->
                        <div
                            class="bg-orange-50 border border-orange-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-orange-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-clock text-orange-600 text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-orange-600 uppercase tracking-wide">Man Hours</p>
                                    <p class="text-sm font-semibold text-gray-900 truncate">
                                        {{ $training->jumlah_man_hours }} jam</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Information Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column - Informasi Dasar & Dokumen -->
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <!-- Basic Information Section -->
                            <h3 class="text-lg font-semibold text-gray-800 mb-5 flex items-center">
                                <i class="fas fa-info-circle text-blue-500 mr-2"></i>
                                Informasi Dasar & Dokumen
                            </h3>

                            <!-- Basic Information -->
                            <div class="space-y-4 mb-8">
                                <div class="flex flex-col space-y-2">
                                    <span class="text-sm font-medium text-gray-600">Judul Pelatihan</span>
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <span class="text-sm text-gray-900 font-medium">{{ $training->judul }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <span class="text-sm font-medium text-gray-600">Penyelenggara</span>
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <span
                                            class="text-sm text-gray-900 font-medium">{{ $training->penyelenggara }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <span class="text-sm font-medium text-gray-600">Keterangan</span>
                                    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                        <span class="text-sm text-gray-900 font-medium">{{ $training->keterangan }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Information Section -->
                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-file-alt text-purple-500 mr-2"></i>
                                    Informasi Dokumen
                                </h4>
                                <div class="space-y-4">
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-medium text-gray-600">Nomor Surat</span>
                                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                            <span
                                                class="text-sm text-gray-900 font-medium">{{ $training->nomor_surat }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-medium text-gray-600">Tanggal Surat</span>
                                        <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
                                            <span class="text-sm text-gray-900 font-medium">
                                                {{ \Carbon\Carbon::parse($training->tanggal_surat)->format('d M Y') }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Export Button -->
                                    <div class="pt-2">
                                        <a href="{{ route('admin.trainings.surat-tugas', $training->id) }}"
                                            class="w-full bg-blue-500 text-white px-4 py-3 rounded-lg hover:bg-blue-600 
                            transition duration-200 flex items-center justify-center font-medium shadow-sm group">
                                            <i class="fas fa-download mr-2 group-hover:animate-bounce"></i>
                                            <span>Download Surat Tugas</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Informasi Biaya & Jadwal -->
                        <div class="bg-white border border-gray-200 rounded-xl p-6 shadow-sm">
                            <!-- Financial Information Section -->
                            <h3 class="text-lg font-semibold text-gray-800 mb-5 flex items-center">
                                <i class="fas fa-money-bill-wave text-yellow-500 mr-2"></i>
                                Informasi Biaya & Jadwal
                            </h3>

                            <!-- Financial Information -->
                            <div class="space-y-4 mb-8">
                                <div class="p-4 bg-yellow-50 rounded-lg border border-yellow-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-coins text-yellow-600 text-sm"></i>
                                            <span class="text-sm font-medium text-yellow-700">Biaya Pelatihan</span>
                                        </div>
                                        <span class="text-lg font-bold text-yellow-800">
                                            Rp {{ number_format($training->biaya_pelatihan, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-ticket-alt text-blue-600 text-sm"></i>
                                            <span class="text-sm font-medium text-blue-700">Tiket Peserta</span>
                                        </div>
                                        <span class="text-lg font-bold text-blue-800">
                                            Rp {{ number_format($training->biaya_tiket_peserta, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Time & Schedule Section -->
                            <div class="border-t border-gray-200 pt-6">
                                <h4 class="text-md font-semibold text-gray-800 mb-4 flex items-center">
                                    <i class="fas fa-calendar-alt text-green-500 mr-2"></i>
                                    Jadwal Pelatihan
                                </h4>
                                <div class="space-y-4">
                                    <div class="p-4 bg-green-50 rounded-lg border border-green-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-green-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fa-solid fa-calendar text-green-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-green-600 uppercase tracking-wide">
                                                    Tanggal Mulai</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_mulai)->format('d M Y') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-red-50 rounded-lg border border-red-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-red-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fa-solid fa-calendar text-red-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-red-600 uppercase tracking-wide">Tanggal
                                                    Berakhir</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_akhir)->format('d M Y') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-blue-50 rounded-lg border border-blue-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-blue-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fas fa-hourglass-half text-blue-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-blue-600 uppercase tracking-wide">Durasi
                                                </p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_mulai)->diffInDays(\Carbon\Carbon::parse($training->tanggal_akhir)) + 1 }}
                                                    hari
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-indigo-50 rounded-lg border border-indigo-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-indigo-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fas fa-book-open text-indigo-600 text-xs"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-indigo-600 uppercase tracking-wide">Jam
                                                    Belajar / Hari</p>
                                                <p class="text-sm font-semibold text-gray-900">
                                                    {{ $training->jam_belajar_per_hari }} jam
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Participant Table -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div
                    class="px-6 py-4 bg-gradient-to-r from-yellow-50 to-white border-b border-gray-200 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                    <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                        <i class="fa-solid fa-clipboard-list text-yellow-500 mr-2"></i>Daftar Peserta
                    </h2>
                    @if ($participants->isNotEmpty())
                        <div class="mt-2 sm:mt-0 text-sm text-gray-500">
                            Menampilkan {{ $participants->count() }} dari {{ $participants->total() }} data
                        </div>
                    @endif
                </div>

                <div class="overflow-x-auto bg-white shadow rounded-lg">
                    <table
                        class="min-w-full bg-white border divide-gray-200 rounded-lg overflow-hidden shadow-md table-auto">
                        <thead class="bg-gray-100 text-sm text-gray-700">
                            <tr>
                                <th class="p-3 text-left">NIK</th>
                                <th class="p-3 text-left">Nama</th>
                                <th class="p-3 text-left">Jabatan</th>
                                <th class="p-3 text-left">Unit</th>
                                <th class="p-3 text-left">Level</th>
                                <th class="p-3 text-left">Sertifikat</th>
                                <th class="p-3 text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-700">
                            @forelse ($training->employees as $employee)
                                <tr class="border-t hover:bg-gray-50 transition duration-150">
                                    <td class="p-3">{{ $employee->nik }}</td>
                                    <td class="p-3">{{ $employee->nama }}</td>
                                    <td class="p-3">{{ $employee->jabatan }}</td>
                                    <td class="p-3">{{ $employee->unit_kerja }}</td>
                                    <td class="p-3">{{ $employee->level }}</td>
                                    <td class="p-3">
                                        @if ($employee->pivot->sertifikat)
                                            <a href="{{ asset('storage/' . $employee->pivot->sertifikat) }}"
                                                target="_blank" class="text-blue-600 underline">Unduh</a>
                                        @else
                                            <span class="text-gray-500">Tidak ada</span>
                                        @endif
                                    </td>
                                    <td class="p-3">
                                        <div class="flex items-center gap-2 justify-center">
                                            <a href="{{ route('admin.trainings.participants.edit', ['training' => $training->id, 'employee' => $employee->nik]) }}"
                                                class="p-2 bg-yellow-400 text-white rounded-md hover:bg-yellow-500 transition"
                                                title="Edit Sertifikat">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form
                                                action="{{ route('admin.trainings.participants.destroy', ['training' => $training->id, 'employee' => $employee->nik]) }}"
                                                method="POST" onsubmit="return confirm('Yakin hapus peserta?')"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="p-2 bg-red-500 text-white rounded-md hover:bg-red-600 transition"
                                                    title="Hapus Peserta">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center">
                                            <div class="bg-gray-100 rounded-full p-3 mb-4">
                                                <i class="fas fa-inbox text-gray-400 text-5xl"></i>
                                            </div>
                                            <h3 class="text-lg font-medium text-gray-900 mb-1">Tidak ada data peserta</h3>
                                            <p class="text-gray-500 max-w-full text-center mb-4">
                                                Belum ada data peserta pelatihan yang ditambahkan saat ini.
                                            </p>
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <x-pagination-ui :data="$participants" />
            </div>
        @endsection
