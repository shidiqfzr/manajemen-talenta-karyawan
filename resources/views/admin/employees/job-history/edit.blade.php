{{-- C:\laragon\www\manajemen-talenta-karyawan\resources\views\admin\employees\job-history\edit.blade.php --}}

@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl mx-auto">
        <!-- Header -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 bg-gradient-to-r from-green-600 to-green-700">
                <!-- Header Content -->
                <div class="relative">
                    <!-- Main Title -->
                    <div class="flex items-start justify-between">
                        <div class="flex items-center">
                            <div
                                class="flex items-center justify-center w-12 h-12 bg-white bg-opacity-20 rounded-xl mr-4 backdrop-blur-sm">
                                <i class="fas fa-briefcase text-white text-xl"></i>
                            </div>
                            <div>
                                <h1 class="text-white text-2xl sm:text-3xl font-bold leading-tight">
                                    Edit Riwayat Jabatan
                                </h1>
                                <p class="text-green-100 text-sm mt-1 flex items-center">
                                    <i class="fas fa-user mr-2 opacity-70"></i>
                                    <span class="font-medium">{{ $employee->nama }}</span>
                                    <span class="mx-2 opacity-50">•</span>
                                    <span class="opacity-80 font-mono">{{ $employee->nik }}</span>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Errors -->
            @if ($errors->any())
                <div class="px-6 pt-4">
                    <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800">
                        <div class="font-semibold mb-2 flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i> Periksa kembali input Anda:
                        </div>
                        <ul class="list-disc pl-5 space-y-1">
                            @foreach ($errors->all() as $err)
                                <li>{{ $err }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            <!-- Form -->
            <form method="POST" action="{{ route('admin.employees.job-history.update', [$employee, $jobHistory]) }}" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <!-- Jabatan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jabatan <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="jabatan" value="{{ old('jabatan', $jobHistory->jabatan) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400"
                            placeholder="Contoh: Asisten/Manajer" required>
                    </div>

                    <!-- Unit Kerja -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Unit Kerja <span
                                class="text-red-500">*</span></label>
                        <input type="text" name="unit_kerja" value="{{ old('unit_kerja', $jobHistory->unit_kerja) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400"
                            placeholder="Contoh: Regional V / PKS XYZ" required>
                    </div>

                    <!-- Level -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Level</label>
                        <input type="text" name="level" value="{{ old('level', $jobHistory->level) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400"
                            placeholder="Contoh: Supervisor/Assistant Manager">
                    </div>

                    <!-- Golongan -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Golongan</label>
                        <input type="text" name="golongan" value="{{ old('golongan', $jobHistory->golongan) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400"
                            placeholder="Contoh: IIIA/IIIB">
                    </div>

                    <!-- TMT Awal -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">TMT Awal <span
                                class="text-red-500">*</span></label>
                        <input type="date" name="tmt_awal" value="{{ old('tmt_awal', $jobHistory->tmt_awal->format('Y-m-d')) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                            required>
                    </div>

                    <!-- TMT Akhir -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">TMT Akhir</label>
                        <input type="date" name="tmt_akhir" value="{{ old('tmt_akhir', $jobHistory->tmt_akhir->format('Y-m-d')) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">Kosongkan jika jabatan ini masih aktif.</p>
                    </div>

                    <!-- Jenis Mutasi -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Mutasi</label>
                        <select name="jenis_mutasi"
                            class="w-full px-4 py-2.5 border rounded-lg bg-white focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">— Pilih Jenis Mutasi —</option>
                            @foreach ($mutasiOptions as $opt)
                                <option value="{{ $opt }}" {{ old('jenis_mutasi', $jobHistory->jenis_mutasi) === $opt ? 'selected' : '' }}>
                                    {{ \Illuminate\Support\Str::of($opt)->replace('_', ' ')->title() }}
                                </option>
                            @endforeach
                        </select>
                        @error('jenis_mutasi')
                            <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Nomor SK -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nomor SK</label>
                        <input type="text" name="nomor_sk" value="{{ old('nomor_sk', $jobHistory->nomor_sk) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400"
                            placeholder="Contoh: SK-123/HRD/2025">
                    </div>

                    <!-- Tanggal SK -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal SK</label>
                        <input type="date" name="tanggal_sk" value="{{ old('tanggal_sk', $jobHistory->tanggal_sk->format('Y-m-d')) }}"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <!-- Catatan -->
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Catatan</label>
                        <textarea name="catatan" rows="4"
                            class="w-full px-4 py-2.5 border rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 placeholder-gray-400"
                            placeholder="Catatan tambahan...">{{ old('catatan', $jobHistory->catatan) }}</textarea>
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-6 flex items-center gap-2">
                    <a href="{{ route('admin.employees.show', $employee) }}"
                        class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition">
                        <i class="fas fa-arrow-left mr-2"></i> Kembali
                    </a>
                    <button type="submit"
                        class="inline-flex items-center px-4 py-2 rounded-lg bg-green-600 text-white font-medium hover:bg-green-700 transition">
                        <i class="fas fa-save mr-2"></i> Update
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection
