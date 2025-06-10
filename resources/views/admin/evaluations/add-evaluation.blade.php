@extends('layouts.admin')

@section('content')
    <div class="max-w-5xl mx-auto p-6 bg-white rounded-xl shadow-lg space-y-8">
        <h1 class="text-2xl font-bold text-center mb-6">Tambah Penilaian Karyawan</h1>

        <form action="{{ route('admin.evaluations.store') }}" method="POST" class="space-y-6">
            @csrf

            {{-- Pilih Karyawan --}}
            <div>
                <label for="nik" class="block text-sm font-semibold text-gray-700 mb-2">Pilih Karyawan</label>
                <select name="nik" id="nik" class="tom-input">
                    <option value=""></option>
                    @foreach ($employees as $emp)
                        <option value="{{ $emp->nik }}">{{ $emp->nik }} - {{ $emp->nama }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Nilai Per Kriteria --}}
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Nilai Per Kriteria</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="nilai_kepemimpinan" class="block text-sm font-semibold">Nilai Kepemimpinan</label>
                        <input type="number" name="nilai_kepemimpinan" id="nilai_kepemimpinan"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="nilai_perilaku_budaya" class="block text-sm font-semibold">Nilai Perilaku Budaya</label>
                        <input type="number" name="nilai_perilaku_budaya" id="nilai_perilaku_budaya"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="nilai_pengalaman_teknis" class="block text-sm font-semibold">Nilai Pengalaman
                            Teknis</label>
                        <input type="number" name="nilai_pengalaman_teknis" id="nilai_pengalaman_teknis"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="nilai_kematangan_pribadi" class="block text-sm font-semibold text-gray-700 mb-1">Nilai
                            Kematangan Pribadi</label>
                        <input type="number" name="nilai_kematangan_pribadi" id="nilai_kematangan_pribadi"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="nilai_tertimbang" class="block text-sm font-semibold text-gray-700 mb-1">Nilai
                            Tertimbang</label>
                        <input type="number" name="nilai_tertimbang" id="nilai_tertimbang"
                            class="w-full border p-2 rounded">
                    </div>
                </div>
            </div>

            {{-- Skor 9 Box --}}
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Skor 9 Box</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="skor_smkbk_9box" class="block text-sm font-semibold text-gray-700 mb-1">Skor
                            SMK-BK</label>
                        <input type="number" name="skor_smkbk_9box" id="skor_smkbk_9box" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="skor_cli_9box" class="block text-sm font-semibold text-gray-700 mb-1">Skor CLI</label>
                        <input type="number" name="skor_cli_9box" id="skor_cli_9box" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="kategori_9box" class="block text-sm font-semibold text-gray-700 mb-1">Kategori</label>
                        <input type="text" name="kategori_9box" id="kategori_9box" class="w-full border p-2 rounded">
                    </div>
                </div>
            </div>

            {{-- Asesmen --}}
            <div>
                <h3 class="text-lg font-semibold mb-2 border-b pb-1">Asesmen</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="lembaga_asesmen" class="block text-sm font-semibold text-gray-700 mb-1">Lembaga
                            Asesmen</label>
                        <input type="text" name="lembaga_asesmen" id="lembaga_asesmen" class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="tanggal_pelaksanaan_asesmen"
                            class="block text-sm font-semibold text-gray-700 mb-1">Tanggal Pelaksanaan</label>
                        <input type="date" name="tanggal_pelaksanaan_asesmen" id="tanggal_pelaksanaan_asesmen"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="hasil_skor_asesmen" class="block text-sm font-semibold text-gray-700 mb-1">Hasil
                            Skor</label>
                        <input type="number" name="hasil_skor_asesmen" id="hasil_skor_asesmen"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="kategori_asesmen" class="block text-sm font-semibold text-gray-700 mb-1">Kategori
                            Asesmen</label>
                        <input type="text" name="kategori_asesmen" id="kategori_asesmen"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="keterangan_asesmen" class="block text-sm font-semibold text-gray-700 mb-1">Keterangan
                            Asesmen</label>
                        <input type="text" name="keterangan_asesmen" id="keterangan_asesmen"
                            class="w-full border p-2 rounded">
                    </div>
                    <div>
                        <label for="expired_asesmen" class="block text-sm font-semibold text-gray-700 mb-1">Expired
                            Asesmen</label>
                        <input type="date" name="expired_asesmen" id="expired_asesmen" class="w-full border p-2 rounded">
                    </div>
                </div>
            </div>

            {{-- Tombol --}}
            <div class="flex justify-end pt-6">
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-semibold px-6 py-2 rounded-lg shadow">
                    Simpan
                </button>
            </div>
        </form>
    </div>
    </div>
@endsection

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.css" rel="stylesheet">
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
        new TomSelect("#nik", {
            placeholder: "Cari NIK atau nama",
            create: false,
            sortField: {
                field: "text",
                direction: "asc"
            }
        });
    </script>
@endpush
