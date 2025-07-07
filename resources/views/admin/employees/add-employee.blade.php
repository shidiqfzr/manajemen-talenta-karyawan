@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
        <h1 class="text-2xl font-bold text-center mb-6">Tambah Karyawan</h1>

        <form id="employeeForm" method="POST" action="{{ route('admin.employees.store') }}" enctype="multipart/form-data"
            class="space-y-8">
            @csrf

            <!-- Informasi Pribadi -->
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Informasi Pribadi</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">NIK</label>
                        <input type="text" name="nik" class="w-full border p-2 rounded" required>
                        @error('nik')
                            <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Nama</label>
                        <input type="text" name="nama" class="w-full border p-2 rounded" required>
                        @error('nama')
                            <p class="text-red-500 text-sm">{{ $message }}</p>
                        @enderror
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tempat Lahir</label>
                        <input type="text" name="tempat_lahir" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Lahir</label>
                        <input type="date" name="tanggal_lahir" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Agama</label>
                        <input type="text" name="agama" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Susunan Keluarga</label>
                        <input type="text" name="susunan_keluarga" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Pendidikan Terakhir</label>
                        <input type="text" name="pendidikan_terakhir" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Sekolah</label>
                        <input type="text" name="sekolah" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Foto</label>
                        <input type="file" name="foto" accept="image/*"
                            class="w-full border p-2 rounded bg-white text-sm text-gray-700" />
                        @error('foto')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror

                        {{-- Optional image preview on edit --}}
                        @if (isset($employee) && $employee->foto)
                            <div class="mt-2">
                                <img src="{{ asset('storage/foto/' . $employee->foto) }}" alt="Foto Karyawan"
                                    class="w-24 h-24 object-cover rounded border" />
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Informasi Pekerjaan -->
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Informasi Pekerjaan</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium">Jabatan</label>
                        <input type="text" name="jabatan" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Level</label>
                        <input type="text" name="level" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Unit Kerja</label>
                        <input type="text" name="unit_kerja" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Golongan</label>
                        <input type="text" name="golongan" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Job Grader</label>
                        <input type="number" name="job_grader" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Person Grade</label>
                        <input type="numbe" name="person_grade" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Dalam Jabatan</label>
                        <input type="date" name="tanggal_dalam_jabatan" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal MBT</label>
                        <input type="date" name="tanggal_mbt" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">TMT Bekerja</label>
                        <input type="date" name="tmt_bekerja" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Diangkat Staf</label>
                        <input type="date" name="tanggal_diangkat_staf" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">TMT Unit Kerja</label>
                        <input type="date" name="tmt_unit_kerja" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label class="block text-sm font-medium">Tanggal Pensiun</label>
                        <input type="date" name="tanggal_pensiun" class="w-full border p-2 rounded">
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.employees.index') }}"
                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Batal</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
@endsection
