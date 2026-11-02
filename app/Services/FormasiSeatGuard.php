<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\Unit;

/**
 * Position control untuk penambahan pegawai manual: jabatan wajib menempati kursi aktif pada Master Formasi
 * dan jumlah pemangku definitif tidak boleh melebihi kuota (DOMAIN.md §3.5, §3.6).
 */
class FormasiSeatGuard
{
    /**
     * Mengembalikan pesan kesalahan bila penempatan tidak diizinkan, atau null bila lolos.
     */
    public function check(string $unitName, string $jabatan, string $level, string $statusPenugasan = 'Definitif'): ?string
    {
        $unit = Unit::where('nama', $unitName)->where('is_active', true)->lockForUpdate()->first();
        if (! $unit) {
            return null; // Keberadaan unit divalidasi di tempat lain; penjaga ini hanya menilai kursi.
        }

        $seat = $unit->positions()
            ->where('positions.is_active', true)
            ->where('positions.level', $level)
            ->where('positions.nama', $jabatan)
            ->first();

        if (! $seat) {
            return 'Jabatan Formasi tidak valid untuk Unit Kerja dan Level Strata Pegawai yang dipilih.';
        }

        if ($statusPenugasan !== 'Definitif' || $seat->is_overflow || $seat->is_diperban) {
            return null; // Jabatan pool dan penugasan non-definitif tidak menghabiskan kuota.
        }

        $kuota = (int) $seat->pivot->kuota;
        $terisi = Employee::where('unit_kerja', $unitName)
            ->where('jabatan', $jabatan)
            ->where('status_penugasan', 'Definitif')
            ->count();

        if ($terisi >= $kuota) {
            return "Kuota formasi jabatan '{$jabatan}' pada Unit Kerja '{$unitName}' sudah terpenuhi ({$kuota} formasi). Gunakan modul Mutasi atau ubah kuota di Master Formasi.";
        }

        return null;
    }
}
