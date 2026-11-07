@extends('layouts.user')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-8">

    {{-- Detail Pelatihan --}}
    <div class="bg-white shadow-md rounded-xl p-6">
        <h1 class="text-3xl font-bold text-surface-800 mb-6 border-b pb-2">{{ $training->judul }}</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-4 gap-x-6 text-sm text-surface-700">
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
    <x-admin.table.card>
        <div class="px-5 py-3 border-b border-surface-200 bg-white">
            <h2 class="text-sm font-bold text-surface-900">Peserta Pelatihan</h2>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead class="bg-surface-50 sticky top-0 z-10">
                    <tr class="border-b border-surface-200 text-[11px] font-bold text-surface-700 uppercase tracking-wider">
                        <x-admin.table.th>Nama</x-admin.table.th>
                        <x-admin.table.th>Jabatan</x-admin.table.th>
                        <x-admin.table.th>Level</x-admin.table.th>
                        <x-admin.table.th>Unit Kerja</x-admin.table.th>
                        <x-admin.table.th>Sertifikat</x-admin.table.th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-surface-100 text-surface-800">
                    @forelse ($training->employees as $employee)
                        <tr class="hover:bg-surface-50/75 transition">
                            <td class="px-4 py-3 font-medium text-surface-900">{{ $employee->nama }}</td>
                            <td class="px-4 py-3 text-surface-700">{{ $employee->jabatan }}</td>
                            <td class="px-4 py-3 text-surface-700">{{ $employee->level }}</td>
                            <td class="px-4 py-3 text-surface-700">{{ $employee->unit_kerja }}</td>
                            <td class="px-4 py-3">
                                @if (!empty($employee->pivot->sertifikat))
                                    <a href="{{ asset('storage/' . $employee->pivot->sertifikat) }}" target="_blank"
                                       class="inline-flex items-center px-3 py-1 bg-info text-white hover:bg-info rounded-md text-xs font-medium transition">
                                        📄 Unduh
                                    </a>
                                @else
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-danger-100 text-danger-700">
                                        Tidak Ada
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-6 text-surface-500">Belum ada peserta.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.table.card>

</div>
@endsection
