<?php

namespace App\Services\Formasi;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Membaca berkas Excel formasi menjadi daftar formasi jabatan per (unit, jabatan). Kelas ini tidak
 * mengakses database.
 *
 * Dua format berkas dikenali dari baris judulnya:
 * - Format struktur organisasi (8 kolom, kuota eksplisit, strata ditulis `RM 5`).
 * - Format lama (4 kolom, satu baris = satu formasi jabatan, `RM-3`).
 */
class FormasiExcelParser
{
    /** Judul kolom format lama. */
    public const HEADERS = ['Bagian/Unit Kerja', 'Nama Jabatan', 'Level RM', 'Bidang'];

    /** Judul kolom format struktur organisasi. */
    public const HEADERS_STRUKTUR = ['No', 'Unit/Bagian', 'Sub Bagian', 'Jabatan', 'Strata RM', 'Bidang', 'Kuota', 'Keterangan'];

    public const RM_LEVELS = ['RM-1', 'RM-2', 'RM-3', 'RM-5'];

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

        $format = $this->detectFormat($rows[0] ?? []);
        if ($format === null) {
            return [
                'seats' => [],
                'errors' => [['row' => 1, 'message' => 'Judul kolom harus: '.implode(', ', self::HEADERS_STRUKTUR).' (atau format lama: '.implode(', ', self::HEADERS).').']],
            ];
        }

        $seats = [];
        $conflicted = [];
        $errors = [];
        // Level RM pertama yang terlihat per jabatan: RM bersifat melekat pada nama jabatan, bukan pada unit.
        $rmByJabatan = [];

        foreach (array_slice($rows, 1, null, true) as $index => $row) {
            $excelRow = $index + 1;
            [$unitRaw, $jabatanRaw, $rm, $bidang, $kuotaRaw] = $this->extractCells($row, $format);

            if ($unitRaw === '' && $jabatanRaw === '' && $rm === '' && $bidang === '') {
                continue;
            }

            $rm = (string) preg_replace('/^RM\s*(\d)$/i', 'RM-$1', $rm);

            $problems = [];
            if ($unitRaw === '' || $jabatanRaw === '') {
                $problems[] = 'Unit kerja dan nama jabatan wajib diisi.';
            }
            if (! in_array($rm, self::RM_LEVELS, true)) {
                $problems[] = "Level RM '{$rm}' tidak valid (".implode(', ', self::RM_LEVELS).').';
            }
            if (! in_array($bidang, self::BIDANG, true)) {
                $problems[] = "Bidang '{$bidang}' tidak valid (KEU, TAN, TEK, UMU).";
            }
            // Kuota kosong = formasi berbasis norma tanpa jumlah tetap (disimpan 0, tidak ada kursi).
            if ($kuotaRaw !== null && $kuotaRaw !== '' && ! ctype_digit($kuotaRaw)) {
                $problems[] = "Kuota '{$kuotaRaw}' tidak valid (bilangan bulat, kosong berarti 0).";
            }
            if ($problems !== []) {
                $errors[] = ['row' => $excelRow, 'message' => implode(' ', $problems)];

                continue;
            }

            $kuota = $kuotaRaw === null ? 1 : (int) $kuotaRaw;

            $unit = FormasiNormalizer::unit($unitRaw);
            $jabatan = FormasiNormalizer::jabatan($jabatanRaw);
            $key = $unit.'|'.$jabatan;

            if (isset($seats[$key]) && ($seats[$key]['rm_level'] !== $rm || $seats[$key]['bidang'] !== $bidang)) {
                $conflicted[$key] = true;
                $errors[] = ['row' => $excelRow, 'message' => "Jabatan '{$jabatan}' di '{$unit}' muncul dengan Level RM atau Bidang yang berbeda."];

                continue;
            }

            if (isset($seats[$key])) {
                $seats[$key]['kuota'] += $kuota;

                continue;
            }

            if (isset($rmByJabatan[$jabatan]) && $rmByJabatan[$jabatan]['rm'] !== $rm) {
                $first = $rmByJabatan[$jabatan];
                $errors[] = ['row' => $excelRow, 'message' => "Jabatan '{$jabatan}' memakai Level RM {$rm} di '{$unit}' tetapi {$first['rm']} di baris {$first['row']}; Level RM harus sama di seluruh unit."];

                continue;
            }
            $rmByJabatan[$jabatan] ??= ['rm' => $rm, 'row' => $excelRow];

            $seats[$key] = ['unit' => $unit, 'jabatan' => $jabatan, 'rm_level' => $rm, 'bidang' => $bidang, 'kuota' => $kuota];
        }

        return [
            'seats' => array_values(array_diff_key($seats, $conflicted)),
            'errors' => $errors,
        ];
    }

    /**
     * @param  array<int, mixed>  $headerRow
     * @return 'struktur'|'legacy'|null
     */
    private function detectFormat(array $headerRow): ?string
    {
        $header = fn (int $length) => array_map(fn ($cell) => trim((string) $cell), array_slice(array_pad($headerRow, $length, ''), 0, $length));

        return match (true) {
            $header(count(self::HEADERS_STRUKTUR)) === self::HEADERS_STRUKTUR => 'struktur',
            $header(count(self::HEADERS)) === self::HEADERS => 'legacy',
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $row
     * @return array{0: string, 1: string, 2: string, 3: string, 4: ?string} unit, jabatan, RM, bidang, kuota (null = satu baris satu kursi)
     */
    private function extractCells(array $row, string $format): array
    {
        $cell = fn (int $column) => trim((string) ($row[$column] ?? ''));

        if ($format === 'struktur') {
            return [$cell(1), $cell(3), $cell(4), $cell(5), $cell(6)];
        }

        return [$cell(0), $cell(1), $cell(2), $cell(3), null];
    }
}
