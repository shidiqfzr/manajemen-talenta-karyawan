<?php

namespace App\Services\Formasi;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Membaca berkas Excel formasi (satu baris = satu kursi) menjadi daftar kursi
 * per (unit, jabatan). Kelas ini tidak mengakses database.
 */
class FormasiExcelParser
{
    public const HEADERS = ['Bagian/Unit Kerja', 'Nama Jabatan', 'Level RM', 'Bidang'];

    public const RM_LEVELS = ['RM-1', 'RM-2', 'RM-3'];

    public const BIDANG = ['KEU', 'TAN', 'TEK', 'UMU'];

    /**
     * @return array{
     *     seats: list<array{unit: string, jabatan: string, rm_level: string, bidang: string, kuota: int}>,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function parse(string $path): array
    {
        $rows = IOFactory::load($path)->getSheet(0)->toArray(null, true, false, false);

        $header = array_map(fn ($cell) => trim((string) $cell), array_slice($rows[0] ?? [], 0, 4));
        if ($header !== self::HEADERS) {
            return [
                'seats' => [],
                'errors' => [['row' => 1, 'message' => 'Judul kolom harus: '.implode(', ', self::HEADERS).'.']],
            ];
        }

        $seats = [];
        $conflicted = [];
        $errors = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $excelRow = $index + 1;
            [$unitRaw, $jabatanRaw, $rm, $bidang] = array_map(fn ($cell) => trim((string) $cell), array_slice(array_pad($row, 4, ''), 0, 4));

            if ($unitRaw === '' && $jabatanRaw === '' && $rm === '' && $bidang === '') {
                continue;
            }

            $problems = [];
            if ($unitRaw === '' || $jabatanRaw === '') {
                $problems[] = 'Unit kerja dan nama jabatan wajib diisi.';
            }
            if (! in_array($rm, self::RM_LEVELS, true)) {
                $problems[] = "Level RM '{$rm}' tidak valid (RM-1 sampai RM-3).";
            }
            if (! in_array($bidang, self::BIDANG, true)) {
                $problems[] = "Bidang '{$bidang}' tidak valid (KEU, TAN, TEK, UMU).";
            }
            if ($problems !== []) {
                $errors[] = ['row' => $excelRow, 'message' => implode(' ', $problems)];

                continue;
            }

            $unit = FormasiNormalizer::unit($unitRaw);
            $jabatan = FormasiNormalizer::jabatan($jabatanRaw);
            $key = $unit.'|'.$jabatan;

            if (isset($seats[$key]) && ($seats[$key]['rm_level'] !== $rm || $seats[$key]['bidang'] !== $bidang)) {
                $conflicted[$key] = true;
                $errors[] = ['row' => $excelRow, 'message' => "Jabatan '{$jabatan}' di '{$unit}' muncul dengan Level RM atau Bidang yang berbeda."];

                continue;
            }

            if (isset($seats[$key])) {
                $seats[$key]['kuota']++;

                continue;
            }

            $seats[$key] = ['unit' => $unit, 'jabatan' => $jabatan, 'rm_level' => $rm, 'bidang' => $bidang, 'kuota' => 1];
        }

        return [
            'seats' => array_values(array_diff_key($seats, $conflicted)),
            'errors' => $errors,
        ];
    }
}
