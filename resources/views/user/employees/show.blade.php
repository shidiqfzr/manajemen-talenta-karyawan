@extends('layouts.user')

@section('title', 'Employee Detail')

@section('content')
    <div class="max-w-6xl mx-auto px-4 sm:px-6 pt-2 pb-10">
        <a href="{{ route('user.employees.index') }}" class="text-blue-600 hover:underline mb-6 inline-block text-sm">
            ← Kembali ke Daftar Karyawan
        </a>

        <div class="bg-white rounded-2xl shadow-md p-6 sm:p-8 hover:shadow-lg transition duration-300">
            <div class="flex flex-col md:flex-row gap-6 md:gap-10">
                <!-- foto Section -->
                <div class="w-full md:w-1/3 flex justify-center md:justify-start">
                    <img src="{{ asset('storage/' . ($employee->foto ?? 'images/default.png')) }}" alt="{{ $employee->nama }}"
                        class="rounded-xl w-56 h-56 sm:w-64 sm:h-64 md:w-72 md:h-72 object-cover border border-gray-300 shadow-sm">
                </div>

                <!-- Employee Info -->
                <div class="w-full md:w-2/3">
                    <!-- Header -->
                    <div class="mb-6">
                        <h1 class="text-2xl sm:text-3xl font-bold text-blue-700">{{ $employee->nama }}</h1>
                        <p class="text-sm text-gray-500 mt-1">NIK: {{ $employee->nik }}</p>
                    </div>

                    @php
                        $sections = [
                            'Informasi Pribadi' => [
                                'Tempat Lahir' => $employee->tempat_lahir,
                                'Tanggal Lahir' => $employee->tanggal_lahir,
                                'Agama' => $employee->agama,
                                'Pendidikan Terakhir' => $employee->pendidikan_terakhir,
                                'Sekolah' => $employee->sekolah,
                                "Susunan Keluarga" => $employee->susunan_keluarga,
                            ],
                            'Informasi Pekerjaan' => [
                                'Jabatan' => $employee->jabatan,
                                'Level' => $employee->level,
                                'Unit Kerja' => $employee->unit_kerja,
                                'Golongan 2024' => $employee->golongan_2024,
                                'Job Grader' => $employee->job_grader,
                                'Person Grade' => $employee->person_grade,
                                'Tanggal Dalam Jabatan' => $employee->tanggal_dalam_jabatan,
                                'Tanggal MBT' => $employee->tanggal_mbt,
                                'TMT Bekerja' => $employee->tmt_bekerja,
                                'Tanggal Diangkat Staf' => $employee->tanggal_diangkat_staf,
                                'TMT Unit Kerja' => $employee->tmt_unit_kerja,
                                'Tanggal Pensiun' => $employee->tanggal_pensiun,
                            ],
                        ];
                    @endphp

                    @foreach ($sections as $sectionTitle => $fields)
                        <div class="mb-8">
                            <h2 class="text-lg sm:text-xl font-semibold text-gray-700 mb-4 border-b pb-1">
                                {{ $sectionTitle }}
                            </h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm text-gray-800">
                                @foreach ($fields as $label => $value)
                                    <p>
                                        <span class="font-medium">{{ $label }}:</span>
                                        <span class="truncate-multiline"
                                            title="{{ $value }}">{{ $value }}</span>
                                    </p>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
@endsection

@push('styles')
    <style>
        .truncate-multiline {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
    </style>
@endpush
