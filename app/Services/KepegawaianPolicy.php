<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Aturan baku kepegawaian PTPN IV Regional V yang dipakai lintas modul (DOMAIN.md §4.1 & §5.1):
 * Batas Usia Pensiun, Masa Bebas Tugas, dan rentang grade per strata.
 *
 * Dipusatkan di satu tempat agar perubahan pedoman cukup satu baris, bukan perburuan angka di
 * model, validator, dan layar.
 */
final class KepegawaianPolicy
{
    /** Batas Usia Pensiun (BUP) normal dalam tahun. */
    public const BATAS_USIA_PENSIUN = 56;

    /** Masa Bebas Tugas (MBT) dimulai sekian bulan sebelum tanggal pensiun (H-6 bulan; DOMAIN.md Bab 5.1). */
    public const MBT_BULAN_SEBELUM_PENSIUN = 6;

    /** Peringatan "Menjelang MBT" tampil sekian bulan sebelum tanggal MBT. */
    public const MBT_PERINGATAN_BULAN = 6;

    /** Rentang Job Grade / Person Grade per strata: [min, max]. */
    public const GRADE_RANGE = [
        'Karpel' => [1, 10],
        'Karpim' => [11, 16],
    ];

    /** Rentang grade keseluruhan (lintas strata). */
    public const GRADE_MIN = 1;

    public const GRADE_MAX = 16;

    /**
     * Tanggal pensiun: awal bulan kelahiran + BUP. Null bila tanggal lahir kosong atau tidak valid.
     */
    public static function tanggalPensiun(?string $tanggalLahir): ?string
    {
        return self::pensiunCarbon($tanggalLahir)?->toDateString();
    }

    public static function pensiunCarbon(?string $tanggalLahir): ?Carbon
    {
        if (empty($tanggalLahir)) {
            return null;
        }

        try {
            return Carbon::parse($tanggalLahir)->addYears(self::BATAS_USIA_PENSIUN)->startOfMonth();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Tanggal MBT: sekian bulan (MBT_BULAN_SEBELUM_PENSIUN) sebelum tanggal pensiun.
     */
    public static function tanggalMbt(?string $tanggalPensiun): ?string
    {
        if (empty($tanggalPensiun)) {
            return null;
        }

        try {
            return Carbon::parse($tanggalPensiun)->subMonthsNoOverflow(self::MBT_BULAN_SEBELUM_PENSIUN)->toDateString();
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Rentang grade yang berlaku untuk strata; null bila strata tidak dikenal.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function gradeRange(string $level): ?array
    {
        return self::GRADE_RANGE[$level] ?? null;
    }
}
