{{--
    Satu-satunya aksi pada Sebaran Formasi: formasi yang masih kosong diisi lewat Mutasi (tercatat pada riwayat jabatan);
    bila sudah dipesan mutasi terjadwal, tampil penanda terjadwal. Formasi penuh tidak memiliki aksi.

    <x-admin.master.seat-action :seat="$seat" />
--}}
@props(['seat'])

@if ($seat->vacant_slots <= 0)
    <span class="text-surface-400" aria-hidden="true">—</span>
@elseif ($seat->is_reserved)
    <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-warning-800" title="Dipesan oleh {{ $seat->reserved_occupant_name }} (TMT: {{ $seat->reserved_tmt }})"><i class="fas fa-lock text-[10px]" aria-hidden="true"></i> Terjadwal (SK)</span>
@elseif ($seat->free_slots <= 0)
    {{-- Layar Mutasi hanya menempatkan ke formasi yang masih punya kursi kosong (kuota dikurangi seluruh pemegang); jangan menawarkan jalan buntu. --}}
    <span class="text-[11px] text-surface-500" title="Seluruh kuota terisi pemegang aktif, termasuk Pj/Plt. Pindahkan salah satu pemegang lebih dulu.">Ada pemegang aktif</span>
@else
    <a href="{{ route('admin.mutasi.create', ['jabatan' => $seat->jabatan, 'unit' => $seat->unit_kerja]) }}"
        class="inline-flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-lg bg-primary-600 hover:bg-primary-700 text-white text-xs font-semibold transition shadow-2xs focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-1"
        title="Isi formasi ini melalui mutasi atau promosi karyawan">
        <i class="fas fa-exchange-alt text-[10px]" aria-hidden="true"></i><span>Isi lewat Mutasi</span>
    </a>
@endif
