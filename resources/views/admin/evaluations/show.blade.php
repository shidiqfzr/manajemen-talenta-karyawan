@extends('layouts.admin')
@section('content')
    <div class="bg-surface-100 min-h-screen">
        <div class="max-w-7xl mx-auto bg-white p-6 rounded-xl shadow-lg">
            <!-- Header Card -->
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 mb-6 overflow-hidden">
                <div class="bg-gradient-to-r from-info to-info-700 px-6 py-4">
                    <h1 class="text-xl sm:text-2xl font-bold text-white">Detail Penilaian Karyawan</h1>
                </div>
                <!-- Employee Information -->
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-surface-50 p-4 rounded-lg">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Nama Karyawan</label>
                            <p class="text-surface-900 font-semibold">{{ $employee->nama ?? 'Tidak ditemukan' }}</p>
                        </div>
                        <div class="bg-surface-50 p-4 rounded-lg">
                            <label class="text-sm font-medium text-surface-600 block mb-1">NIK</label>
                            <p class="text-surface-900 font-semibold">{{ $evaluation->employee_nik }}</p>
                        </div>
                        <div class="bg-surface-50 p-4 rounded-lg">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Bidang Tugas</label>
                            <p class="text-surface-900 font-semibold">{{ $evaluation->bidang_tugas ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Evaluation Scores Card -->
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 mb-6 overflow-hidden">
                <div class="border-b border-surface-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-surface-900 flex items-center">
                        <i class="fas fa-chart-bar w-5 h-5 mr-2 text-info" aria-hidden="true"></i>
                        Nilai Per Kriteria
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                        <div class="bg-surface-50 p-4 rounded-lg border border-surface-200">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Kepemimpinan</label>
                            <p class="text-xl font-bold text-surface-900">{{ $evaluation->nilai_kepemimpinan ?? '-' }}</p>
                        </div>
                        <div class="bg-surface-50 p-4 rounded-lg border border-surface-200">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Perilaku Budaya</label>
                            <p class="text-xl font-bold text-surface-900">{{ $evaluation->nilai_perilaku_budaya ?? '-' }}</p>
                        </div>
                        <div class="bg-surface-50 p-4 rounded-lg border border-surface-200">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Pengalaman Teknis</label>
                            <p class="text-xl font-bold text-surface-900">{{ $evaluation->nilai_pengalaman_teknis ?? '-' }}
                            </p>
                        </div>
                        <div class="bg-surface-50 p-4 rounded-lg border border-surface-200">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Kematangan Pribadi</label>
                            <p class="text-xl font-bold text-surface-900">{{ $evaluation->nilai_kematangan_pribadi ?? '-' }}
                            </p>
                        </div>
                        <div
                            class="bg-primary-50 p-4 rounded-lg border border-primary-200 sm:col-span-2 lg:col-span-1">
                            <label class="text-sm font-medium text-primary-700 block mb-1">Nilai Tertimbang</label>
                            <p class="text-xl font-bold text-primary-800">{{ $evaluation->nilai_tertimbang ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- 9 Box Score Card -->
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 mb-6 overflow-hidden">
                <div class="border-b border-surface-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-surface-900 flex items-center">
                        <i class="fas fa-heart w-5 h-5 mr-2 text-primary-600" aria-hidden="true"></i>
                        Skor 9 Box
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-surface-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Skor SMKBK</label>
                            <p class="text-lg font-semibold text-surface-900">{{ $evaluation->skor_smkbk_9box ?? '-' }}</p>
                        </div>
                        <div class="bg-surface-50 p-4 rounded-lg border">
                            <label class="text-sm font-medium text-surface-600 block mb-1">Skor CLI</label>
                            <p class="text-lg font-semibold text-surface-900">{{ $evaluation->skor_cli_9box ?? '-' }}</p>
                        </div>
                        <div class="bg-gradient-to-br from-warning-50 to-warning-100 p-4 rounded-lg border border-warning-200">
                            <label class="text-sm font-medium text-warning-700 block mb-1">Kategori 9 Box</label>
                            <p class="text-lg font-semibold text-warning-800">{{ $evaluation->kategori_9box ?? '-' }}</p>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Assessment Card -->
            <div class="bg-white rounded-xl shadow-sm border border-surface-200 overflow-hidden">
                <div class="border-b border-surface-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-surface-900 flex items-center">
                        <i class="fas fa-file-alt w-5 h-5 mr-2 text-surface-500" aria-hidden="true"></i>
                        Informasi Asesmen
                    </h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-surface-600 block mb-1">Lembaga Asesmen</label>
                                <p class="text-surface-900 bg-surface-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->lembaga_asesmen ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-surface-600 block mb-1">Tanggal Pelaksanaan</label>
                                <p class="text-surface-900 bg-surface-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->tanggal_pelaksanaan_asesmen ? \Carbon\Carbon::parse($evaluation->tanggal_pelaksanaan_asesmen)->format('d-m-Y') : '-' }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-surface-600 block mb-1">Hasil Skor</label>
                                <p class="text-surface-900 bg-surface-50 px-3 py-2 rounded-md font-semibold">
                                    {{ $evaluation->hasil_skor_asesmen ?? '-' }}</p>
                            </div>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label class="text-sm font-medium text-surface-600 block mb-1">Kategori</label>
                                <p class="text-surface-900 bg-surface-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->kategori_asesmen ?? '-' }}</p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-surface-600 block mb-1">Tanggal Expired</label>
                                <p class="text-surface-900 bg-surface-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->expired_asesmen ? \Carbon\Carbon::parse($evaluation->expired_asesmen)->format('d-m-Y') : '-' }}
                                </p>
                            </div>
                            <div>
                                <label class="text-sm font-medium text-surface-600 block mb-1">Keterangan</label>
                                <p class="text-surface-900 bg-surface-50 px-3 py-2 rounded-md">
                                    {{ $evaluation->keterangan_asesmen ?? '-' }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Back Button -->
            <div class="mt-8 flex justify-center sm:justify-start">
                <a href="{{ route('admin.evaluations.index') }}"
                    class="inline-flex items-center px-6 py-3 rounded-lg border border-surface-300 text-surface-700 hover:bg-surface-50 transition-colors duration-200">
                    <i class="fas fa-arrow-left mr-2" aria-hidden="true"></i> Kembali
                </a>
            </div>
        </div>
    </div>
@endsection
