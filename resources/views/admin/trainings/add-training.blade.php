@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg">
    <h1 class="text-2xl font-bold text-center text-gray-800 mb-8">Tambah Data Pelatihan</h1>

    <form action="{{ route('admin.trainings.store') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Judul Pelatihan</label>
                <input type="text" name="judul" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200 focus:border-blue-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jenis Pelatihan</label>
                <input type="text" name="jenis" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200 focus:border-blue-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Metode</label>
                <input type="text" name="metode" class="w-full border border-gray-300 rounded px-3 py-2 focus:ring focus:ring-blue-200 focus:border-blue-500" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Biaya Tiket Peserta (Rp)</label>
                <input type="number" name="biaya_tiket_peserta" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Biaya Pelatihan (Rp)</label>
                <input type="number" name="biaya_pelatihan" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Penyelenggara</label>
                <input type="text" name="penyelenggara" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nomor Surat</label>
                <input type="text" name="nomor_surat" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tanggal Surat</label>
                <input type="date" name="tanggal_surat" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Bidang Pelatihan</label>
                <input type="text" name="bidang_pelatihan" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jam Belajar per Hari</label>
                <input type="text" name="jam_belajar_per_hari" class="w-full border border-gray-300 rounded px-3 py-2" required>
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Keterangan</label>
            <textarea name="keterangan" rows="4" class="w-full border border-gray-300 rounded px-3 py-2" required></textarea>
        </div>

        <!-- Tombol Aksi -->
        <div class="flex justify-end space-x-3">
            <a href="{{ route('admin.trainings.index') }}" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Batal</a>
            <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Simpan</button>
        </div>
    </form>
</div>
@endsection
