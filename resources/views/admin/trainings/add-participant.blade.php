@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
        <h1 class="text-2xl font-bold text-center text-gray-800">Tambah Peserta ke: {{ $training->judul }}</h1>

        <form action="{{ route('admin.trainings.participants.store', $training->id) }}" method="POST"
            enctype="multipart/form-data" class="space-y-6">
            @csrf

            {{-- Pilih Karyawan --}}
            <div>
                <label for="employee_niks" class="block text-sm font-semibold text-gray-700 mb-2">Pilih Karyawan</label>
                <select name="employee_niks[]" id="employee_niks" multiple
                    class="w-full tom-input border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200 focus:border-blue-500">
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->nik }}">{{ $emp->nik }} - {{ $emp->nama }}</option>
                    @endforeach
                </select>
                @error('employee_niks')
                    <p class="text-sm text-red-500 mt-1">{{ $message }}</p>
                @enderror
            </div>

            <!-- Upload Sertifikat -->
            <div>
                <label for="sertifikat" class="block text-sm font-medium text-gray-700 mb-1">Upload Sertifikat <span
                        class="text-gray-500">(Opsional)</span></label>
                <input type="file" name="sertifikat" id="sertifikat" accept="application/pdf,image/*"
                    class="w-full px-3 py-2 border rounded-md shadow-sm focus:ring focus:ring-blue-300 focus:border-blue-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                <p class="text-xs text-gray-500 mt-1">Format yang didukung: PDF, JPG, PNG.</p>
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

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        new TomSelect("#employee_niks", {
            placeholder: "Pilih beberapa karyawan...",
            plugins: ['remove_button'],
            persist: false,
            maxOptions: 500,
            closeAfterSelect: false,
            sortField: {
                field: "text",
                direction: "asc"
            }
        });
    </script>
@endpush