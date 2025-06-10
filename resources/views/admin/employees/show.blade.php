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
                            @if ($employee->foto)
                                <img src="{{ asset('storage/' . $employee->foto) }}" alt="Foto Karyawan"
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
                            <label class="text-sm font-medium text-green-700 block mb-1">Golongan 2024</label>
                            <p class="text-green-900 font-semibold">{{ $employee->golongan_2024 }}</p>
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
            <!-- Back Button -->
            <div class="mt-8 flex justify-center sm:justify-start">
                <a href="{{ route('admin.employees.index') }}"
                    class="inline-flex items-center px-6 py-3 bg-gradient-to-r from-gray-600 to-gray-700 text-white font-medium rounded-lg hover:from-gray-700 hover:to-gray-800 transition-all duration-200 shadow-md hover:shadow-lg transform hover:-translate-y-0.5">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
@endsection