@extends('layouts.admin')

@section('content')
<div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
    <h1 class="text-2xl font-bold mb-6">Edit Data Pelatihan</h1>
    
    <form action="{{ route('admin.trainings.update', $training->id) }}" method="POST">
        @csrf
        @method('PUT')

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block font-medium">Judul</label>
                <input type="text" name="judul" value="{{ old('judul', $training->judul) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Jenis</label>
                <input type="text" name="jenis" value="{{ old('jenis', $training->jenis) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Metode</label>
                <input type="text" name="metode" value="{{ old('metode', $training->metode) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" value="{{ old('tanggal_mulai', $training->tanggal_mulai) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Tanggal Akhir</label>
                <input type="date" name="tanggal_akhir" value="{{ old('tanggal_akhir', $training->tanggal_akhir) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Tiket Peserta</label>
                <input type="number" name="biaya_tiket_peserta" value="{{ old('biaya_tiket_peserta', $training->biaya_tiket_peserta) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Biaya Pelatihan</label>
                <input type="number" name="biaya_pelatihan" value="{{ old('biaya_pelatihan', $training->biaya_pelatihan) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Penyelenggara</label>
                <input type="text" name="penyelenggara" value="{{ old('penyelenggara', $training->penyelenggara) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Nomor Surat</label>
                <input type="text" name="nomor_surat" value="{{ old('nomor_surat', $training->nomor_surat) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Tanggal Surat</label>
                <input type="date" name="tanggal_surat" value="{{ old('tanggal_surat', $training->tanggal_surat) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div class="md:col-span-2">
                <label class="block font-medium">Keterangan</label>
                <textarea name="keterangan" class="w-full border rounded px-3 py-2" rows="3">{{ old('keterangan', $training->keterangan) }}</textarea>
            </div>

            <div>
                <label class="block font-medium">Bidang Pelatihan</label>
                <input type="text" name="bidang_pelatihan" value="{{ old('bidang_pelatihan', $training->bidang_pelatihan) }}" class="w-full border rounded px-3 py-2">
            </div>

            <div>
                <label class="block font-medium">Jam Belajar/Hari</label>
                <input type="text" name="jam_belajar_per_hari" value="{{ old('jam_belajar_per_hari', $training->jam_belajar_per_hari) }}" class="w-full border rounded px-3 py-2">
            </div>
        </div>

        <div class="mt-6">
            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700">
                Simpan
            </button>
        </div>
    </form>
</div>
@endsection
