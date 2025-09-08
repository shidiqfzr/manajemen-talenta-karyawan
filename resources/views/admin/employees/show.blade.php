@extends('layouts.admin')
@section('content')
    <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
        <div class="max-w-5xl mx-auto">

            <!-- Header Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <h1 class="text-xl sm:text-2xl font-bold text-white flex items-center">
                        <i class="fas fa-user w-6 h-6 mr-3"></i>
                        Detail Karyawan
                    </h1>
                </div>

                <!-- Profile Section -->
                <div class="p-6">
                    <div class="flex flex-col lg:flex-row items-start gap-6">
                        <!-- Profile Photo -->
                        <div
                            class="w-32 h-32 sm:w-40 sm:h-40 bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl overflow-hidden shadow-md border-4 border-white mx-auto lg:mx-0 flex-shrink-0">
                            @if ($employee->foto && Storage::disk('public')->exists($employee->foto))
                                <img src="{{ Storage::disk('public')->url($employee->foto) }}" alt="Foto Karyawan"
                                    class="w-full h-full object-cover">
                            @else
                                <div class="flex flex-col items-center justify-center w-full h-full text-gray-400">
                                    <i class="fas fa-user text-4xl mb-2"></i>
                                    <span class="text-xs text-center">Tidak ada foto</span>
                                </div>
                            @endif
                        </div>

                        <!-- Basic Info -->
                        <div class="flex-1 text-center lg:text-left">
                            <h2 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">{{ $employee->nama }}</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
                                <div class="bg-blue-50 px-4 py-3 rounded-lg border border-blue-200">
                                    <label class="text-sm font-medium text-blue-700 block">NIK</label>
                                    <p class="text-blue-900 font-semibold">{{ $employee->nik }}</p>
                                </div>
                                <div class="bg-green-50 px-4 py-3 rounded-lg border border-green-200">
                                    <label class="text-sm font-medium text-green-700 block">Jabatan</label>
                                    <p class="text-green-900 font-semibold">{{ $employee->jabatan }}</p>
                                </div>
                                <div class="bg-purple-50 px-4 py-3 rounded-lg border border-purple-200 sm:col-span-2">
                                    <label class="text-sm font-medium text-purple-700 block">Unit Kerja</label>
                                    <p class="text-purple-900 font-semibold">{{ $employee->unit_kerja }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Personal Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-id-card text-orange-600 mr-2"></i>
                        Informasi Pribadi
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tempat Lahir</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tempat_lahir }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Lahir</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tanggal_lahir }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Agama</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->agama }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Susunan Keluarga</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->susunan_keluarga }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Pendidikan Terakhir</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->pendidikan_terakhir }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Sekolah</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->sekolah }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Work Information Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h3 class="text-lg font-semibold text-gray-900 flex items-center">
                        <i class="fas fa-briefcase text-blue-600 mr-2"></i>
                        Informasi Pekerjaan
                    </h3>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                            <label class="text-sm font-medium text-blue-700 block mb-1">Level</label>
                            <p class="text-blue-900 font-semibold">{{ $employee->level }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                            <label class="text-sm font-medium text-green-700 block mb-1">Golongan</label>
                            <p class="text-green-900 font-semibold">{{ $employee->golongan }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                            <label class="text-sm font-medium text-purple-700 block mb-1">Job Grader</label>
                            <p class="text-purple-900 font-semibold">{{ $employee->job_grader }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
                            <label class="text-sm font-medium text-orange-700 block mb-1">Person Grade</label>
                            <p class="text-orange-900 font-semibold">{{ $employee->person_grade }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Dalam Jabatan</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tanggal_dalam_jabatan }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal MBT</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tanggal_mbt }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">TMT Bekerja</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tmt_bekerja }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Diangkat Staf</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tanggal_diangkat_staf }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">TMT Unit Kerja</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->tmt_unit_kerja }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-red-50 to-red-100 p-4 rounded-lg border border-red-200">
                            <label class="text-sm font-medium text-red-700 block mb-1">Tanggal Pensiun</label>
                            <p class="text-red-900 font-semibold">{{ $employee->tanggal_pensiun }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Training Information -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-indigo-50 to-purple-50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-2 sm:mb-0">
                            <i class="fas fa-chalkboard-teacher text-indigo-600 mr-2"></i>
                            Riwayat Pelatihan
                        </h3>
                        @if ($trainings->count())
                            <div class="flex items-center text-sm text-gray-600 bg-white px-3 py-1 rounded-full border">
                                <i class="fas fa-certificate text-indigo-500 mr-1"></i>
                                {{ $trainings->total() }} Pelatihan
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    @if ($trainings->isEmpty())
                        <!-- Empty State -->
                        <div class="text-center py-12">
                            <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-graduation-cap text-3xl text-gray-400"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Belum Ada Riwayat Pelatihan</h4>
                            <p class="text-gray-500 max-w-sm mx-auto">
                                Karyawan ini belum mengikuti pelatihan apapun. Riwayat pelatihan akan muncul di sini ketika
                                tersedia.
                            </p>
                        </div>
                    @else
                        <!-- Training Table -->
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col"
                                            class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            No
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Judul Pelatihan
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tanggal
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Sertifikat
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach ($trainings as $index => $training)
                                        <tr class="hover:bg-gray-50 transition-colors duration-150">
                                            <!-- Number -->
                                            <td class="px-4 py-4 whitespace-nowrap text-sm text-left text-gray-700">
                                                {{ $trainings->firstItem() + $index }}
                                            </td>

                                            <!-- Training Title -->
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div>
                                                        <a href="{{ route('admin.trainings.show', $training->id) }}"
                                                            class="text-sm font-medium text-gray-900 hover:text-indigo-600 transition-colors duration-150 cursor-pointer">
                                                            {{ $training->judul }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Date -->
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                <div class="flex items-center">
                                                    {{ \Carbon\Carbon::parse($training->tanggal_mulai)->format('d M Y') }}
                                                    -
                                                    {{ \Carbon\Carbon::parse($training->tanggal_akhir)->format('d M Y') }}
                                                </div>
                                            </td>

                                            <!-- Certificate -->
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                @if ($training->pivot->sertifikat)
                                                    <a href="{{ asset('storage/' . $training->pivot->sertifikat) }}"
                                                        target="_blank"
                                                        class="inline-flex items-center px-3 py-1.5 bg-green-100 text-green-700 text-xs font-medium rounded-full hover:bg-green-200 transition-colors duration-150"
                                                        title="Unduh Sertifikat">
                                                        <i class="fas fa-download mr-1"></i>
                                                        Unduh
                                                    </a>
                                                @else
                                                    <span
                                                        class="inline-flex items-center px-3 py-1.5 bg-amber-100 text-amber-700 text-xs font-medium rounded-full"
                                                        title="Sertifikat belum tersedia">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Tidak Ada
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <x-pagination-ui :data="$trainings" />
                    @endif
                </div>
            </div>

            {{-- === Evaluations Section === --}}
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mt-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4 bg-gradient-to-r from-emerald-50 to-teal-50">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 flex items-center mb-2 sm:mb-0">
                            <i class="fas fa-chart-line text-emerald-600 mr-2"></i>
                            Evaluasi Kinerja & Potensi
                        </h3>
                        @isset($evaluations)
                            @if ($evaluations->count())
                                <div class="flex items-center text-sm text-gray-600 bg-white px-3 py-1 rounded-full border">
                                    <i class="fas fa-file-alt text-emerald-500 mr-1"></i>
                                    {{ $evaluations->total() }} Data Evaluasi
                                </div>
                            @endif
                        @endisset
                    </div>
                </div>

                <div>
                    @if (empty($evaluations) || $evaluations->isEmpty())
                        {{-- Empty State --}}
                        <div class="text-center py-12">
                            <div class="w-24 h-24 mx-auto mb-4 bg-gray-100 rounded-full flex items-center justify-center">
                                <i class="fas fa-clipboard-list text-3xl text-gray-400"></i>
                            </div>
                            <h4 class="text-lg font-medium text-gray-900 mb-2">Belum Ada Data Evaluasi</h4>
                            <p class="text-gray-500 max-w-sm mx-auto">
                                Tambahkan data evaluasi karyawan untuk melihat ringkasan nilai, 9-Box, dan informasi asesmen
                                di sini.
                            </p>
                        </div>
                    @else
                        @php
                            // Ambil evaluasi terbaru untuk ringkasan kartu atas
                            $ev = $evaluations->first();
                        @endphp

                        {{-- Ringkasan Evaluasi Terbaru --}}
                        <div class="p-6">
                            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                                {{-- Kartu Nilai Tertimbang --}}
                                <div
                                    class="bg-gradient-to-br from-emerald-50 to-emerald-100 p-5 rounded-lg border border-emerald-200">
                                    <label class="text-sm font-medium text-emerald-700 block mb-1">Nilai Tertimbang</label>
                                    <p class="text-3xl font-extrabold text-emerald-900">
                                        {{ number_format($ev->nilai_tertimbang ?? 0, 2) }}
                                    </p>
                                    <p class="text-xs text-emerald-800 mt-1">
                                        Ringkasan gabungan bobot (Kepemimpinan 40%, Perilaku 30%, Pengalaman 20%, Kematangan
                                        10%).
                                    </p>
                                </div>

                                {{-- Kartu 9-Box --}}
                                <div
                                    class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-5 rounded-lg border border-indigo-200">
                                    <label class="text-sm font-medium text-indigo-700 block mb-1">9-Box</label>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm text-indigo-900">
                                                SMKBK: <span
                                                    class="font-semibold">{{ number_format($ev->skor_smkbk_9box ?? 0, 2) }}</span>
                                            </p>
                                            <p class="text-sm text-indigo-900">
                                                CLI: <span
                                                    class="font-semibold">{{ number_format($ev->skor_cli_9box ?? 0, 2) }}</span>
                                            </p>
                                        </div>
                                        <span
                                            class="inline-flex items-center px-3 py-1.5 rounded-full text-xs font-medium
                                {{ $ev->kategori_9box ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700' }}">
                                            {{ $ev->kategori_9box ?? 'Tidak dikategorikan' }}
                                        </span>
                                    </div>
                                </div>

                                {{-- Kartu Bidang Tugas --}}
                                <div
                                    class="bg-gradient-to-br from-amber-50 to-amber-100 p-5 rounded-lg border border-amber-200">
                                    <label class="text-sm font-medium text-amber-700 block mb-1">Bidang Tugas</label>
                                    <p class="text-amber-900 font-semibold">
                                        {{ $ev->bidang_tugas ?? '-' }}
                                    </p>
                                    @if ($ev->tanggal_pelaksanaan_asesmen)
                                        <p class="text-xs text-amber-800 mt-1">
                                            Terakhir asesmen:
                                            {{ \Carbon\Carbon::parse($ev->tanggal_pelaksanaan_asesmen)->format('d M Y') }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            {{-- Breakdown Nilai Per Kriteria --}}
                            <div class="mt-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
                                @php
                                    $kriterias = [
                                        [
                                            'label' => 'Kepemimpinan',
                                            'value' => $ev->nilai_kepemimpinan,
                                            'color' => 'blue',
                                        ],
                                        [
                                            'label' => 'Perilaku Budaya',
                                            'value' => $ev->nilai_perilaku_budaya,
                                            'color' => 'green',
                                        ],
                                        [
                                            'label' => 'Pengalaman Teknis',
                                            'value' => $ev->nilai_pengalaman_teknis,
                                            'color' => 'purple',
                                        ],
                                        [
                                            'label' => 'Kematangan Pribadi',
                                            'value' => $ev->nilai_kematangan_pribadi,
                                            'color' => 'orange',
                                        ],
                                    ];
                                @endphp

                                @foreach ($kriterias as $kr)
                                    <div class="bg-gray-50 p-4 rounded-lg border">
                                        <label
                                            class="text-sm font-medium text-gray-600 block mb-1">{{ $kr['label'] }}</label>
                                        <p class="text-gray-900 font-semibold mb-2">
                                            {{ is_null($kr['value']) ? '-' : number_format($kr['value'], 2) }}
                                        </p>
                                        @if (!is_null($kr['value']))
                                            <div class="w-full bg-gray-200 rounded-full h-2">
                                                @php $pct = max(0, min(100, (float)$kr['value'])); @endphp
                                                <div class="h-2 rounded-full bg-{{ $kr['color'] }}-500"
                                                    style="width: {{ $pct }}%"></div>
                                            </div>
                                            <p class="text-xs text-gray-500 mt-1">{{ $pct }}%</p>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            {{-- Informasi Asesmen --}}
                            <div class="mt-6">
                                <h4 class="text-md font-semibold text-gray-900 mb-3 flex items-center">
                                    <i class="fas fa-user-check text-teal-600 mr-2"></i>
                                    Informasi Asesmen
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                                    <div class="bg-white p-4 rounded-lg border">
                                        <label class="text-sm font-medium text-gray-600 block mb-1">Lembaga Asesmen</label>
                                        <p class="text-gray-900 font-semibold">{{ $ev->lembaga_asesmen ?? '-' }}</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border">
                                        <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal
                                            Pelaksanaan</label>
                                        <p class="text-gray-900 font-semibold">
                                            {{ $ev->tanggal_pelaksanaan_asesmen ? \Carbon\Carbon::parse($ev->tanggal_pelaksanaan_asesmen)->format('d M Y') : '-' }}
                                        </p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border">
                                        <label class="text-sm font-medium text-gray-600 block mb-1">Hasil Skor</label>
                                        <p class="text-gray-900 font-semibold">
                                            {{ is_null($ev->hasil_skor_asesmen) ? '-' : number_format($ev->hasil_skor_asesmen, 2) }}
                                        </p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border">
                                        <label class="text-sm font-medium text-gray-600 block mb-1">Kategori
                                            Asesmen</label>
                                        <p class="text-gray-900 font-semibold">{{ $ev->kategori_asesmen ?? '-' }}</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border">
                                        <label class="text-sm font-medium text-gray-600 block mb-1">Keterangan</label>
                                        <p class="text-gray-900">{{ $ev->keterangan_asesmen ?? '-' }}</p>
                                    </div>
                                    <div class="bg-white p-4 rounded-lg border">
                                        <label class="text-sm font-medium text-gray-600 block mb-1">Masa Berlaku</label>
                                        <p class="text-gray-900 font-semibold">
                                            {{ $ev->expired_asesmen ? \Carbon\Carbon::parse($ev->expired_asesmen)->format('d M Y') : '-' }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {{-- Catatan Umum --}}
                            @if (!empty($ev->keterangan))
                                <div class="mt-6 bg-gray-50 p-4 rounded-lg border">
                                    <label class="text-sm font-medium text-gray-600 block mb-1">Catatan</label>
                                    <p class="text-gray-900">{{ $ev->keterangan }}</p>
                                </div>
                            @endif
                        </div>

                        {{-- Tabel Riwayat Evaluasi --}}
                        <div class="border-t border-gray-200">
                            <div class="px-6 py-4">
                                <h4 class="text-md font-semibold text-gray-900 flex items-center">
                                    <i class="fas fa-history text-gray-600 mr-2"></i>
                                    Riwayat Evaluasi
                                </h4>
                            </div>
                            <div class="overflow-x-auto px-6 pb-6">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">No
                                            </th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Tanggal Input</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Nilai Tertimbang</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                9-Box</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">
                                                Asesmen</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        @foreach ($evaluations as $i => $e)
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-4 text-sm text-gray-700">
                                                    {{ $evaluations->firstItem() + $i }}
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    {{ $e->created_at?->timezone('Asia/Pontianak')->format('d M Y H:i') }}
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    {{ is_null($e->nilai_tertimbang) ? '-' : number_format($e->nilai_tertimbang, 2) }}
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    SMKBK {{ number_format($e->skor_smkbk_9box ?? 0, 2) }} /
                                                    CLI {{ number_format($e->skor_cli_9box ?? 0, 2) }}
                                                    @if ($e->kategori_9box)
                                                        <span
                                                            class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-indigo-600 text-white">
                                                            {{ $e->kategori_9box }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 text-sm text-gray-900">
                                                    {{ $e->lembaga_asesmen ?? '-' }}
                                                    @if ($e->tanggal_pelaksanaan_asesmen)
                                                        <span class="text-gray-500">
                                                            ({{ \Carbon\Carbon::parse($e->tanggal_pelaksanaan_asesmen)->format('d M Y') }})
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                {{-- Pagination --}}
                                <div class="mt-4">
                                    <x-pagination-ui :data="$evaluations" />
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-8 flex justify-center sm:justify-start">
                <a href="{{ route('admin.employees.index') }}"
                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white font-medium rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                    Kembali
                </a>
            </div>
        </div>
    </div>
@endsection
