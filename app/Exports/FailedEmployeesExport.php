<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class FailedEmployeesExport implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
{
    public function __construct(protected array $failedRows) {}

    public function title(): string
    {
        return 'Baris Gagal Validasi';
    }

    public function headings(): array
    {
        return [
            'NO. BARIS EXCEL',
            'ALASAN PENOLAKAN / KESALAHAN SISTEM',
            'NIK*',
            'NAMA*',
            'JENIS KELAMIN',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'AGAMA',
            'SUSUNAN KELUARGA',
            'STATUS KARYAWAN*',
            'JABATAN*',
            'LEVEL*',
            'UNIT KERJA*',
            'GOLONGAN',
            'RM LEVEL',
            'STATUS PENUGASAN',
            'TUGAS RANGKAP / PENUGASAN SEMENTARA',
            'TMT BEKERJA',
            'TANGGAL DIANGKAT STAF',
            'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA',
            'JALUR MASUK',
            'JOB GRADE',
            'PERSON GRADE',
            'PENDIDIKAN TERAKHIR',
            'SEKOLAH',
            'TANGGAL MBT',
            'TANGGAL PENSIUN',
        ];
    }

    public function array(): array
    {
        $exportData = [];

        foreach ($this->failedRows as $item) {
            $rowNum = $item['row'] ?? '';
            $reasons = is_array($item['errors']) ? implode('; ', $item['errors']) : ($item['errors'] ?? '');
            $raw = $item['raw_data'] ?? [];

            $exportData[] = [
                $rowNum,
                self::sanitizeCellValue($reasons),
                $this->findRawValue($raw, ['nik']),
                $this->findRawValue($raw, ['nama']),
                $this->findRawValue($raw, ['jenis_kelamin', 'jk']),
                $this->findRawValue($raw, ['tempat_lahir']),
                $this->findRawValue($raw, ['tanggal_lahir', 'tgl_lahir']),
                $this->findRawValue($raw, ['agama']),
                $this->findRawValue($raw, ['susunan_keluarga', 'ptkp']),
                $this->findRawValue($raw, ['status_karyawan']),
                $this->findRawValue($raw, ['jabatan']),
                $this->findRawValue($raw, ['level', 'strata']),
                $this->findRawValue($raw, ['unit_kerja', 'unit']),
                $this->findRawValue($raw, ['golongan', 'gol']),
                $this->findRawValue($raw, ['rm_level', 'rm']),
                $this->findRawValue($raw, ['status_penugasan']),
                $this->findRawValue($raw, ['tugas_rangkap_penugasan_sementara', 'tugas_rangkap']),
                $this->findRawValue($raw, ['tmt_bekerja']),
                $this->findRawValue($raw, ['tanggal_diangkat_staf', 'tgl_staf']),
                $this->findRawValue($raw, ['tanggal_dalam_jabatan', 'tgl_jabatan']),
                $this->findRawValue($raw, ['tmt_unit_kerja', 'tmt_unit']),
                $this->findRawValue($raw, ['jalur_masuk']),
                $this->findRawValue($raw, ['job_grade']),
                $this->findRawValue($raw, ['person_grade']),
                $this->findRawValue($raw, ['pendidikan_terakhir', 'pendidikan']),
                $this->findRawValue($raw, ['sekolah', 'universitas']),
                $this->findRawValue($raw, ['tanggal_mbt', 'mbt']),
                $this->findRawValue($raw, ['tanggal_pensiun', 'pensiun']),
            ];
        }

        return $exportData;
    }

    /**
     * Mitigasi CWE-1236 (Formula / CSV Injection).
     * Mencegah karakter formula (=, +, -, @, \t, \r) dieksekusi oleh aplikasi spreadsheet.
     */
    public static function sanitizeCellValue(mixed $val): string
    {
        $str = (string) ($val ?? '');
        if ($str === '') {
            return '';
        }

        if (in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }

    protected function findRawValue(array $raw, array $possibleKeys): string
    {
        foreach ($raw as $k => $v) {
            $cleanKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', trim(str_replace('*', '', (string) $k))), '_'));
            foreach ($possibleKeys as $pk) {
                $cleanPk = strtolower(trim(str_replace('*', '', $pk)));
                if ($cleanKey === $cleanPk || str_contains($cleanKey, $cleanPk)) {
                    return self::sanitizeCellValue($v);
                }
            }
        }

        return '';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Freeze baris pertama agar header selalu terlihat
                $sheet->freezePane('C2');

                // 2. Tinggi baris header
                $sheet->getRowDimension(1)->setRowHeight(28);

                // 3. Styling Header Utama: Red Burgundy Korporat (#991B1B)
                $sheet->getStyle('A1:AB1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 10,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '991B1B'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'FDA4AF'],
                        ],
                    ],
                ]);

                // 4. Highlight Kolom B (Alasan Penolakan)
                $highestRow = $sheet->getHighestRow();
                if ($highestRow > 1) {
                    $sheet->getStyle("A2:A{$highestRow}")->applyFromArray([
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                        ],
                    ]);

                    $sheet->getStyle("B2:B{$highestRow}")->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => '991B1B'],
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'FFF1F2'],
                        ],
                        'alignment' => [
                            'wrapText' => true,
                            'vertical' => Alignment::VERTICAL_CENTER,
                        ],
                    ]);
                }
            },
        ];
    }
}
