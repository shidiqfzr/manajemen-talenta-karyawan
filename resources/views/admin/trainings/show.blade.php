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
            <div class="bg-white p-6 rounded-2xl shadow-lg mb-8">
                <h2 class="text-xl font-semibold mb-4 text-gray-800 border-b pb-2">Informasi Pelatihan</h2>
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-sm text-gray-700">

                    <!-- Dasar Pelatihan -->
                    <div>
                        <dt class="font-medium text-gray-600">Judul Pelatihan</dt>
                        <dd>{{ $training->judul }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Jenis Pelatihan</dt>
                        <dd>{{ $training->jenis }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Metode Pelatihan</dt>
                        <dd>{{ $training->metode }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Bidang Pelatihan</dt>
                        <dd>{{ $training->bidang_pelatihan }}</dd>
                    </div>

                    <!-- Waktu Pelaksanaan -->
                    <div>
                        <dt class="font-medium text-gray-600">Tanggal Mulai Pelatihan</dt>
                        <dd>{{ $training->tanggal_mulai }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Tanggal Akhir Pelatihan</dt>
                        <dd>{{ $training->tanggal_akhir }}</dd>
                    </div>

                    <!-- Administrasi & Logistik -->
                    <div>
                        <dt class="font-medium text-gray-600">Tiket Peserta</dt>
                        <dd>Rp {{ number_format($training->biaya_tiket_peserta, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Biaya Pelatihan</dt>
                        <dd>Rp {{ number_format($training->biaya_pelatihan, 0, ',', '.') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Penyelenggara</dt>
                        <dd>{{ $training->penyelenggara }}</dd>
                    </div>

                    <!-- Surat & Dokumen -->
                    <div>
                        <dt class="font-medium text-gray-600">Nomor Surat</dt>
                        <dd>{{ $training->nomor_surat }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Tanggal Surat</dt>
                        <dd>{{ $training->tanggal_surat }}</dd>
                    </div>

                    <!-- Jam & Man Hours -->
                    <div>
                        <dt class="font-medium text-gray-600">Jam Belajar/Hari</dt>
                        <dd>{{ $training->jam_belajar_per_hari }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-600">Man Hours Pelatihan</dt>
                        <dd>{{ $training->jumlah_man_hours }}</dd>
                    </div>

                    <!-- Keterangan -->
                    <div class="md:col-span-2">
                        <dt class="font-medium text-gray-600">Keterangan</dt>
                        <dd class="text-gray-800">{{ $training->keterangan ?? '-' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Table -->
            <div class="overflow-x-auto bg-white shadow rounded-lg">
                <table class="min-w-full bg-white border divide-gray-200 rounded-lg overflow-hidden shadow-md table-auto">
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
                    <tbody>
                        @forelse ($training->employees as $employee)
                            <tr class="text-sm text-gray-700">
                                <td class="p-3">{{ $employee->nik }}</td>
                                <td class="p-3">{{ $employee->nama }}</td>
                                <td class="p-3">{{ $employee->jabatan }}</td>
                                <td class="p-3">{{ $employee->unit_kerja }}</td>
                                <td class="p-3">{{ $employee->level }}</td>
                                <td class="p-3">
                                    @if ($employee->pivot->sertifikat)
                                        <a href="{{ asset('storage/' . $employee->pivot->sertifikat) }}" target="_blank"
                                            class="text-blue-600 underline">Unduh</a>
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
                                            method="POST" onsubmit="return confirm('Yakin hapus peserta?')" class="inline">
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
                                <td colspan="6" class="px-6 py-12 text-center">
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
