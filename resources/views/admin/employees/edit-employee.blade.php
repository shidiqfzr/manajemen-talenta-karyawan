@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
        <h2 class="text-2xl font-bold text-center">Edit Karyawan - {{ $employee->nama }}</h2>

        @if (session('success'))
            <div class="bg-green-100 text-green-700 p-3 rounded mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form action="{{ route('admin.employees.update', $employee->nik) }}" method="POST" enctype="multipart/form-data"
            class="space-y-8">
            @csrf
            @method('PUT')

            {{-- Basic Info --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium">NIK</label>
                    <input type="text" value="{{ $employee->nik }}" disabled
                        class="w-full border p-2 rounded bg-gray-100">
                </div>
                <div>
                    <label class="block text-sm font-medium">Nama</label>
                    <input type="text" name="nama" value="{{ old('nama', $employee->nama) }}"
                        class="w-full border p-2 rounded">
                    @error('nama')
                        <p class="text-red-500 text-sm">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Informasi Pribadi --}}
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Informasi Pribadi</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" value="{{ old('tempat_lahir', $employee->tempat_lahir) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir"
                            value="{{ old('tanggal_lahir', $employee->tanggal_lahir) }}" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Agama</label>
                        <input type="text" name="agama" value="{{ old('agama', $employee->agama) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Susunan Keluarga</label>
                        <input type="text" name="susunan_keluarga"
                            value="{{ old('susunan_keluarga', $employee->susunan_keluarga) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Pendidikan Terakhir</label>
                        <input type="text" name="pendidikan_terakhir"
                            value="{{ old('pendidikan_terakhir', $employee->pendidikan_terakhir) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Sekolah</label>
                        <input type="text" name="sekolah" value="{{ old('sekolah', $employee->sekolah) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Foto</label>
                        <input type="file" name="foto"
                            class="w-full border p-2 rounded bg-white text-sm text-gray-700" accept="image/*">
                        @error('foto')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror

                        @if ($employee->foto)
                            <div class="mt-2">
                                <img src="{{ asset('storage/' . $employee->foto) }}" alt="Foto"
                                    class="w-24 h-24 rounded border object-cover">
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Informasi Pekerjaan --}}
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Informasi Pekerjaan</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Jabatan</label>
                        <input type="text" name="jabatan" value="{{ old('jabatan', $employee->jabatan) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Level</label>
                        <input type="text" name="level" value="{{ old('level', $employee->level) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Unit Kerja</label>
                        <input type="text" name="unit_kerja" value="{{ old('unit_kerja', $employee->unit_kerja) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Golongan</label>
                        <input type="text" name="golongan"
                            value="{{ old('golongan', $employee->golongan) }}" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Job Grader</label>
                        <input type="number" name="job_grader" value="{{ old('job_grader', $employee->job_grader) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Person Grade</label>
                        <input type="number" name="person_grade"
                            value="{{ old('person_grade', $employee->person_grade) }}" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Dalam Jabatan</label>
                        <input type="date" name="tanggal_dalam_jabatan"
                            value="{{ old('tanggal_dalam_jabatan', $employee->tanggal_dalam_jabatan) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal MBT</label>
                        <input type="date" name="tanggal_mbt" value="{{ old('tanggal_mbt', $employee->tanggal_mbt) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">TMT Bekerja</label>
                        <input type="date" name="tmt_bekerja"
                            value="{{ old('tmt_bekerja', $employee->tmt_bekerja) }}" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Diangkat Staf</label>
                        <input type="date" name="tanggal_diangkat_staf"
                            value="{{ old('tanggal_diangkat_staf', $employee->tanggal_diangkat_staf) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">TMT Unit Kerja</label>
                        <input type="date" name="tmt_unit_kerja"
                            value="{{ old('tmt_unit_kerja', $employee->tmt_unit_kerja) }}"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Pensiun</label>
                        <input type="date" name="tanggal_pensiun"
                            value="{{ old('tanggal_pensiun', $employee->tanggal_pensiun) }}"
                            class="w-full border p-2 rounded">
                    </div>
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.employees.index') }}"
                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Batal</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Update</button>
            </div>
        </form>
    </div>
@endsection
