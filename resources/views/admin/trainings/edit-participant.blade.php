@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
        <h1 class="text-2xl font-bold text-center text-gray-800">Edit Sertifikat Peserta</h1>

        <div class="text-center text-gray-600">
            <p><strong>NIK:</strong> {{ $employee->nik }}</p>
            <p><strong>Nama:</strong> {{ $employee->nama }}</p>
        </div>

        <form action="{{ route('admin.trainings.participants.update', ['training' => $training->id, 'employee' => $employee->nik]) }}"
              method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Upload Sertifikat -->
            <div>
                <label for="sertifikat" class="block text-sm font-medium text-gray-700 mb-1">Upload Sertifikat Baru <span
                        class="text-gray-500">(Opsional)</span></label>

                @if ($pivotData->sertifikat)
                    <p class="mb-2 text-sm">
                        Sertifikat saat ini:
                        <a href="{{ asset('storage/' . $pivotData->sertifikat) }}" target="_blank"
                            class="text-blue-600 underline">Lihat Sertifikat</a>
                    </p>
                @endif

                <input type="file" name="sertifikat" id="sertifikat" accept="application/pdf,image/*"
                    class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring focus:ring-blue-300 focus:border-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Format: PDF, JPG, PNG. Max 2MB.</p>
                @error('sertifikat')
                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Tombol Aksi -->
            <div class="flex justify-end space-x-3">
                <a href="{{ route('admin.trainings.show', $training->id) }}"
                    class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Batal</a>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan</button>
            </div>
        </form>
    </div>
@endsection
