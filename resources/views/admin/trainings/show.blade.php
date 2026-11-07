@extends('layouts.admin')

@section('content')
    <div class="bg-surface-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-surface-800">{{ $training->judul }}</h1>
                    <p class="text-surface-500 mt-1">Detail informasi dari pelatihan</p>
                </div>
                <a href="{{ route('admin.trainings.participants.create', $training->id) }}"
                    class="w-full sm:w-auto bg-info text-white px-6 py-2.5 rounded-lg hover:bg-info-700 transition ease-in-out duration-200 flex items-center justify-center shadow-sm">
                    <i class="fas fa-plus mr-2" aria-hidden="true"></i> Tambah Peserta
                </a>
            </div>

            <!-- Detail Info -->
            <div
                class="bg-gradient-to-br from-white to-surface-50 border border-surface-200 rounded-2xl shadow-lg mb-8 overflow-hidden">
                <!-- Header Section -->
                <div class="bg-gradient-to-r from-info to-info-700 px-6 py-5">
                    <div class="flex items-center space-x-3">
                        <div class="bg-white/20 rounded-full p-2.5">
                            <i class="fas fa-graduation-cap text-white text-lg" aria-hidden="true"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-semibold text-white">Informasi Pelatihan</h2>
                            <p class="text-info-100 text-sm mt-1">Detail lengkap mengenai pelatihan ini</p>
                        </div>
                    </div>
                </div>

                <!-- Content Section -->
                <div class="p-6">
                    <!-- Training Overview Cards -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
                        <!-- Training Type Card -->
                        <div
                            class="bg-info-50 border border-info-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-info-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-tag text-info text-sm" aria-hidden="true"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-info uppercase tracking-wide">Jenis</p>
                                    <p class="text-sm font-semibold text-surface-900 truncate" title="{{ $training->jenis }}">
                                        {{ $training->jenis }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Method Card -->
                        <div
                            class="bg-primary-50 border border-primary-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-primary-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-chalkboard-teacher text-primary-600 text-sm" aria-hidden="true"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-primary-600 uppercase tracking-wide">Metode</p>
                                    <p class="text-sm font-semibold text-surface-900 truncate" title="{{ $training->metode }}">
                                        {{ $training->metode }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Field Card -->
                        <div
                            class="bg-surface-50 border border-surface-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-surface-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-book text-surface-600 text-sm" aria-hidden="true"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-surface-600 uppercase tracking-wide">Bidang</p>
                                    <p class="text-sm font-semibold text-surface-900 truncate"
                                        title="{{ $training->bidang_pelatihan }}">{{ $training->bidang_pelatihan }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Duration Card -->
                        <div
                            class="bg-surface-50 border border-surface-200 rounded-xl p-4 hover:shadow-md transition-all duration-200 hover:scale-105">
                            <div class="flex items-center space-x-3">
                                <div class="bg-surface-100 rounded-full p-2 flex-shrink-0">
                                    <i class="fas fa-clock text-surface-600 text-sm" aria-hidden="true"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-xs font-medium text-surface-600 uppercase tracking-wide">Man Hours</p>
                                    <p class="text-sm font-semibold text-surface-900 truncate">
                                        {{ $training->jumlah_man_hours }} jam</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Detailed Information Grid -->
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Left Column - Informasi Dasar & Dokumen -->
                        <div class="bg-white border border-surface-200 rounded-xl p-6 shadow-sm">
                            <!-- Basic Information Section -->
                            <h3 class="text-lg font-semibold text-surface-800 mb-5 flex items-center">
                                <i class="fas fa-info-circle text-info mr-2" aria-hidden="true"></i>
                                Informasi Dasar & Dokumen
                            </h3>

                            <!-- Basic Information -->
                            <div class="space-y-4 mb-8">
                                <div class="flex flex-col space-y-2">
                                    <span class="text-sm font-medium text-surface-600">Judul Pelatihan</span>
                                    <div class="p-3 bg-surface-50 rounded-lg border border-surface-200">
                                        <span class="text-sm text-surface-900 font-medium">{{ $training->judul }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <span class="text-sm font-medium text-surface-600">Penyelenggara</span>
                                    <div class="p-3 bg-surface-50 rounded-lg border border-surface-200">
                                        <span
                                            class="text-sm text-surface-900 font-medium">{{ $training->penyelenggara }}</span>
                                    </div>
                                </div>
                                <div class="flex flex-col space-y-2">
                                    <span class="text-sm font-medium text-surface-600">Keterangan</span>
                                    <div class="p-3 bg-surface-50 rounded-lg border border-surface-200">
                                        <span class="text-sm text-surface-900 font-medium">{{ $training->keterangan }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Information Section -->
                            <div class="border-t border-surface-200 pt-6">
                                <h4 class="text-md font-semibold text-surface-800 mb-4 flex items-center">
                                    <i class="fas fa-file-alt text-surface-500 mr-2" aria-hidden="true"></i>
                                    Informasi Dokumen
                                </h4>
                                <div class="space-y-4">
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-medium text-surface-600">Nomor Surat</span>
                                        <div class="p-3 bg-surface-50 rounded-lg border border-surface-200">
                                            <span
                                                class="text-sm text-surface-900 font-medium">{{ $training->nomor_surat }}</span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col space-y-2">
                                        <span class="text-sm font-medium text-surface-600">Tanggal Surat</span>
                                        <div class="p-3 bg-surface-50 rounded-lg border border-surface-200">
                                            <span class="text-sm text-surface-900 font-medium">
                                                {{ \Carbon\Carbon::parse($training->tanggal_surat)->format('d M Y') }}
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Export Button -->
                                    <div class="pt-2">
                                        <a href="{{ route('admin.trainings.surat-tugas', $training->id) }}"
                                            class="w-full bg-info text-white px-4 py-3 rounded-lg hover:bg-info 
                            transition duration-200 flex items-center justify-center font-medium shadow-sm group">
                                            <i class="fas fa-download mr-2 group-hover:animate-bounce" aria-hidden="true"></i>
                                            <span>Download Surat Tugas</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column - Informasi Biaya & Jadwal -->
                        <div class="bg-white border border-surface-200 rounded-xl p-6 shadow-sm">
                            <!-- Financial Information Section -->
                            <h3 class="text-lg font-semibold text-surface-800 mb-5 flex items-center">
                                <i class="fas fa-money-bill-wave text-warning-700 mr-2" aria-hidden="true"></i>
                                Informasi Biaya & Jadwal
                            </h3>

                            <!-- Financial Information -->
                            <div class="space-y-4 mb-8">
                                <div class="p-4 bg-warning-50 rounded-lg border border-warning-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-coins text-warning-700 text-sm" aria-hidden="true"></i>
                                            <span class="text-sm font-medium text-warning-700">Biaya Pelatihan</span>
                                        </div>
                                        <span class="text-lg font-bold text-warning-800">
                                            Rp {{ number_format($training->biaya_pelatihan, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                                <div class="p-4 bg-info-50 rounded-lg border border-info-200">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-2">
                                            <i class="fas fa-ticket-alt text-info text-sm" aria-hidden="true"></i>
                                            <span class="text-sm font-medium text-info-700">Tiket Peserta</span>
                                        </div>
                                        <span class="text-lg font-bold text-info-800">
                                            Rp {{ number_format($training->biaya_tiket_peserta, 0, ',', '.') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            <!-- Time & Schedule Section -->
                            <div class="border-t border-surface-200 pt-6">
                                <h4 class="text-md font-semibold text-surface-800 mb-4 flex items-center">
                                    <i class="fas fa-calendar-alt text-primary-500 mr-2" aria-hidden="true"></i>
                                    Jadwal Pelatihan
                                </h4>
                                <div class="space-y-4">
                                    <div class="p-4 bg-primary-50 rounded-lg border border-primary-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-primary-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fa-solid fa-calendar text-primary-600 text-xs" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-primary-600 uppercase tracking-wide">
                                                    Tanggal Mulai</p>
                                                <p class="text-sm font-semibold text-surface-900">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_mulai)->format('d M Y') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-danger-50 rounded-lg border border-danger-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-danger-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fa-solid fa-calendar text-danger text-xs" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-danger uppercase tracking-wide">Tanggal
                                                    Berakhir</p>
                                                <p class="text-sm font-semibold text-surface-900">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_akhir)->format('d M Y') }}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-info-50 rounded-lg border border-info-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-info-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fas fa-hourglass-half text-info text-xs" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-info uppercase tracking-wide">Durasi
                                                </p>
                                                <p class="text-sm font-semibold text-surface-900">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_mulai)->diffInDays(\Carbon\Carbon::parse($training->tanggal_akhir)) + 1 }}
                                                    hari
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="p-4 bg-surface-50 rounded-lg border border-surface-200">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-surface-100 rounded-full p-2 flex-shrink-0">
                                                <i class="fas fa-book-open text-surface-600 text-xs" aria-hidden="true"></i>
                                            </div>
                                            <div>
                                                <p class="text-xs font-medium text-surface-600 uppercase tracking-wide">Jam
                                                    Belajar / Hari</p>
                                                <p class="text-sm font-semibold text-surface-900">
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

            {{-- Daftar peserta: tabel data bersama dengan paginasi sendiri --}}
            <div class="bg-white rounded-xl border border-surface-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 bg-gradient-to-r from-warning-50 to-white border-b border-surface-200">
                    <h2 class="text-lg font-semibold text-surface-800 flex items-center">
                        <i class="fa-solid fa-clipboard-list text-warning-700 mr-2" aria-hidden="true"></i>Daftar Peserta
                    </h2>
                </div>

                @livewire('admin.training-participant-table', ['trainingId' => $training->id], key('peserta-pelatihan-' . $training->id))
            </div>
        @endsection
