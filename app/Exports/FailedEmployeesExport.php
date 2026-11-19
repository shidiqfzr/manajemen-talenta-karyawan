<?php

namespace App\Exports;

use App\Services\Import\EmployeeImportColumns;
use App\Support\SpreadsheetCell;
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
            ...EmployeeImportColumns::headings(EmployeeImportColumns::LAYOUT_TABULAR, true),
        ];
    }

    public function array(): array
    {
        $exportData = [];

        foreach ($this->failedRows as $item) {
            $reasons = is_array($item['errors']) ? implode('; ', $item['errors']) : ($item['errors'] ?? '');
            $raw = $item['raw_data'] ?? [];

            // Nilai asli setiap kolom dicari pada baris mentah menurut nama bidang dan nama alternatifnya (daftar kolom bersama).
            $exportData[] = [
                $item['row'] ?? '',
                self::sanitizeCellValue($reasons),
                ...array_map(
                    fn (string $field): string => $this->findRawValue($raw, EmployeeImportColumns::rawKeys($field)),
                    EmployeeImportColumns::fields(EmployeeImportColumns::LAYOUT_TABULAR)
                ),
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
        return SpreadsheetCell::neutralise($val);
    }

    protected function findRawValue(array $raw, array $possibleKeys): string
    {
        foreach ($raw as $k => $v) {
            $cleanKey = EmployeeImportColumns::normaliseHeading((string) $k);
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
