<?php

namespace App\Services\Import;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Normalisasi tanggal impor Data Karyawan ke Y-m-d: serial numerik Excel, dd/mm/yyyy, yyyy-mm-dd, lalu penguraian umum.
 * Nilai yang tidak terbaca menjadi null (pemeriksaan wajib-isi dan format dilakukan validator baris).
 */
class ImportDateParser
{
    /** Kolom tanggal pada baris impor yang dinormalisasi. */
    public const FIELDS = [
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'tanggal_mbt',
        'tanggal_pensiun',
    ];

    public function normalize(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Tangani tanggal serial numerik dari Excel
        if (is_numeric($value)) {
            return $this->parseExcelDate($value);
        }

        // Coba format umum dd/mm/yyyy
        if ($date = $this->parseDateFormat($value, 'd/m/Y')) {
            return $date;
        }

        // Coba format yyyy-mm-dd
        if ($date = $this->parseDateFormat($value, 'Y-m-d')) {
            return $date;
        }

        // Fallback parsing generik Carbon
        return $this->parseGenericDate($value);
    }

    private function parseExcelDate(string $value): ?string
    {
        try {
            return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug("Failed to parse Excel date: {$value}");

            return null;
        }
    }

    private function parseDateFormat(string $value, string $format): ?string
    {
        try {
            return Carbon::createFromFormat($format, $value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug("Failed to parse date with format {$format}: {$value}");

            return null;
        }
    }

    private function parseGenericDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug("Failed to parse date generically: {$value}");

            return null;
        }
    }
}
