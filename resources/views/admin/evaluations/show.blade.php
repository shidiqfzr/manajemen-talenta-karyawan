@extends('layouts.admin')

@section('content')
    <div class="bg-gray-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Header Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
                    <h1 class="text-xl sm:text-2xl font-bold text-white">Detail Penilaian Karyawan</h1>
                </div>

                <!-- Employee Information -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Nama Karyawan</label>
                            <p class="text-gray-900 font-semibold">{{ $employee->nama ?? 'Tidak ditemukan' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-sm font-medium text-gray-600 block mb-1">NIK</label>
                            <p class="text-gray-900 font-semibold">{{ $evaluation->nik }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Bidang Tugas</label>
                            <p class="text-gray-900 font-semibold">{{ $evaluation->bidang_tugas ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Evaluation Scores Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                            </path>
                        </svg>
                        Nilai Per Kriteria
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 p-4 rounded-lg border border-blue-200">
                            <label class="text-sm font-medium text-blue-700 block mb-1">Kepemimpinan</label>
                            <p class="text-xl font-bold text-blue-900">{{ $evaluation->nilai_kepemimpinan ?? '-' }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-green-50 to-green-100 p-4 rounded-lg border border-green-200">
                            <label class="text-sm font-medium text-green-700 block mb-1">Perilaku Budaya</label>
                            <p class="text-xl font-bold text-green-900">{{ $evaluation->nilai_perilaku_budaya ?? '-' }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 p-4 rounded-lg border border-purple-200">
                            <label class="text-sm font-medium text-purple-700 block mb-1">Pengalaman Teknis</label>
                            <p class="text-xl font-bold text-purple-900">{{ $evaluation->nilai_pengalaman_teknis ?? '-' }}
                            </p>
                        </div>
                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 p-4 rounded-lg border border-orange-200">
                            <label class="text-sm font-medium text-orange-700 block mb-1">Kematangan Pribadi</label>
                            <p class="text-xl font-bold text-orange-900">{{ $evaluation->nilai_kematangan_pribadi ?? '-' }}
                            </p>
                        </div>
                        <div
                            class="bg-gradient-to-br from-indigo-50 to-indigo-100 p-4 rounded-lg border border-indigo-200 sm:col-span-2 lg:col-span-1">
                            <label class="text-sm font-medium text-indigo-700 block mb-1">Nilai Tertimbang</label>
                            <p class="text-xl font-bold text-indigo-900">{{ $evaluation->nilai_tertimbang ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 9 Box Score Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z">
                            </path>
                        </svg>
                        Skor 9 Box
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Skor SMKBK</label>
                            <p class="text-lg font-semibold text-gray-900">{{ $evaluation->skor_smkbk_9box ?? '-' }}</p>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Skor CLI</label>
                            <p class="text-lg font-semibold text-gray-900">{{ $evaluation->skor_cli_9box ?? '-' }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 p-4 rounded-lg border border-yellow-200">
                            <label class="text-sm font-medium text-yellow-700 block mb-1">Kategori 9 Box</label>
                            <p class="text-lg font-semibold text-yellow-900">{{ $evaluation->kategori_9box ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Assessment Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900 flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z">
                            </path>
                        </svg>
                        Informasi Asesmen
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 block mb-1">Lembaga Asesmen</label>
                                <p class="text-gray-900 bg-gray-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->lembaga_asesmen ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Pelaksanaan</label>
                                <p class="text-gray-900 bg-gray-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->tanggal_pelaksanaan_asesmen ? \Carbon\Carbon::parse($evaluation->tanggal_pelaksanaan_asesmen)->format('d-m-Y') : '-' }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 block mb-1">Hasil Skor</label>
                                <p class="text-gray-900 bg-gray-50 px-3 py-2 rounded-md font-semibold">
                                    {{ $evaluation->hasil_skor_asesmen ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-gray-600 block mb-1">Kategori</label>
                                <p class="text-gray-900 bg-gray-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->kategori_asesmen ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-gray-600 block mb-1">Tanggal Expired</label>
                                <p class="text-gray-900 bg-gray-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->expired_asesmen ? \Carbon\Carbon::parse($evaluation->expired_asesmen)->format('d-m-Y') : '-' }}
                                </p>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="text-sm font-medium text-gray-600 block mb-1">Keterangan</label>
                            <p class="text-gray-900 bg-gray-50 px-3 py-2 rounded-md min-h-[60px]">
                                {{ $evaluation->keterangan_asesmen ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-8 flex justify-center sm:justify-start">
                <a href="{{ route('admin.employees.index') }}"
                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white font-medium rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
@endsection
