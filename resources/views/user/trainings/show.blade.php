@extends('layouts.user')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

    {{-- Detail Pelatihan --}}
    <div class="bg-white shadow-md rounded-xl p-6">
        <h1 class="text-3xl font-bold text-gray-800 mb-6 border-b pb-2">{{ $training->judul }}</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm text-gray-700">
            <div><span class="font-semibold">Tanggal:</span> {{ $training->tanggal_mulai }}</div>
            <div><span class="font-semibold">Jenis:</span> {{ $training->jenis }}</div>
            <div><span class="font-semibold">Metode:</span> {{ $training->metode }}</div>
            <div><span class="font-semibold">Penyelenggara:</span> {{ $training->penyelenggara }}</div>
            <div><span class="font-semibold">Tiket Peserta:</span> Rp{{ number_format($training->biaya_tiket_peserta, 0, ',', '.') }}</div>
            <div><span class="font-semibold">Biaya Pelatihan:</span> Rp{{ number_format($training->biaya_pelatihan, 0, ',', '.') }}</div>
            <div><span class="font-semibold">Jumlah Hari Pembelajaran:</span> {{ $training->jumlah_hari }}</div>
            <div><span class="font-semibold">Keterangan:</span> {{ $training->keterangan }}</div>
            <div><span class="font-semibold">Bidang Pelatihan:</span> {{ $training->bidang_pelatihan }}</div>
            <div><span class="font-semibold">Jumlah Peserta:</span> {{ $training->employees->count() }}</div>
        </div>
    </div>

    {{-- Tabel Peserta --}}
    <div class="bg-white shadow-md rounded-xl p-6">
        <h2 class="text-2xl font-semibold text-gray-800 mb-4">Peserta Pelatihan</h2>

        <div class="overflow-x-auto rounded-md">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Nama</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Jabatan</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Level</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Unit Kerja</th>
                        <th class="px-4 py-3 text-left font-semibold text-gray-600">Sertifikat</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-100">
                    @forelse ($training->employees as $employee)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-2 text-gray-900">{{ $employee->nama }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $employee->jabatan }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $employee->level }}</td>
                            <td class="px-4 py-2 text-gray-700">{{ $employee->unit_kerja }}</td>
                            <td class="px-4 py-2">
                                @if (!empty($employee->pivot->sertifikat))
                                    <a href="{{ asset('storage/' . $employee->pivot->sertifikat) }}" target="_blank"
                                       class="inline-flex items-center px-3 py-1 bg-blue-500 text-white hover:bg-blue-600 rounded-md text-xs font-medium transition">
                                        📄 Unduh
                                    </a>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700">
                                        Tidak Ada
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-center py-4 text-gray-500">Belum ada peserta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
