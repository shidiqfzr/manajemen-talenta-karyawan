<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Ekspor Matriks Formasi & Kebutuhan Tenaga Kerja (MPP) ke Excel.
 * Angka disimpan sebagai numerik agar dapat dijumlah/disaring langsung di Excel.
 */
class MppMatrixExport implements FromArray, WithEvents, WithHeadings, WithProperties, WithTitle
{
    private const RM_LEVELS = ['RM-1', 'RM-2', 'RM-3'];

    private const FIELDS = ['KEU', 'TAN', 'TEK', 'UMU'];

    /**
     * @param  array<int, array<string, mixed>>  $matrix  Baris matriks dari ManPowerPlanningService::getMppMatrix().
     * @param  string  $periodLabel  Mis. "Posisi per 2026-11" atau "Posisi per 2026-10 (ditutup, rev 1)".
     */
    public function __construct(
        private readonly array $matrix,
        private readonly string $periodLabel,
        private readonly ?User $user = null,
    ) {}

    public function title(): string
    {
        return 'Matriks MPP';
    }

    /** Metadata dokumen sesuai UU PDP No. 27/2022 dan tata kelola keamanan informasi BUMN. */
    public function properties(): array
    {
        $userName = $this->user?->name ?? 'Administrator HRIS';
        $userNik = $this->user?->nik ?? 'ADMIN';

        return [
            'creator' => "{$userName} ({$userNik})",
            'lastModifiedBy' => 'PTPN IV Regional V HRIS System',
            'title' => 'Matriks Formasi & Kebutuhan Tenaga Kerja (MPP) — '.$this->periodLabel,
            'description' => 'Dokumen Rahasia Perencanaan Formasi diekspor sesuai UU PDP No. 27/2022',
            'subject' => 'Man Power Planning',
            'keywords' => 'ptpn4, hris, mpp, formasi, confidential',
            'category' => 'HR Confidential Document',
            'manager' => 'Bagian SDM & TI PTPN IV Regional V',
            'company' => 'PT Perkebunan Nusantara IV (Persero) Regional V',
        ];
    }

    public function headings(): array
    {
        $headings = ['No.', 'URAIAN INDIKATOR'];
        foreach (self::RM_LEVELS as $rm) {
            foreach (self::FIELDS as $field) {
                $headings[] = "{$rm} {$field}";
            }
            $headings[] = "JUMLAH {$rm}";
        }

        return [...$headings, 'TOTAL', 'TOTAL KEKURANGAN', 'TOTAL SURPLUS'];
    }

    public function array(): array
    {
        return array_map(function (array $row): array {
            $cells = [$row['no'], $row['label']];
            foreach (self::RM_LEVELS as $rm) {
                foreach (self::FIELDS as $field) {
                    $cells[] = $row['levels'][$rm]['fields'][$field] ?? 0;
                }
                $cells[] = $row['levels'][$rm]['subtotal'] ?? 0;
            }

            // Kekurangan/surplus hanya terisi pada baris Kebutuhan; selain itu sel dibiarkan kosong.
            return [...$cells, $row['grand_total'] ?? 0, $row['shortage_total'] ?? null, $row['surplus_total'] ?? null];
        }, array_values($this->matrix));
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $lastCol = $sheet->getHighestColumn();

                $sheet->freezePane('C2');
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5330']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                ]);

                foreach (range('A', $lastCol) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->getStyle('C2:'.$lastCol.$sheet->getHighestRow())
                    ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Pengganti tombol Cetak: lembar siap cetak A4 landscape, satu halaman lebar.
                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setRowsToRepeatAtTopByStartAndEnd(1, 1);

                $userName = $this->user?->name ?? 'Admin SDM';
                $sheet->getHeaderFooter()
                    ->setOddHeader('&L&8'.$this->periodLabel)
                    ->setOddFooter('&L&8Dokumen Rahasia PT Perkebunan Nusantara IV Regional V - UU PDP No. 27/2022&R&8Diunduh oleh: '.$userName.' (&D &T) - Hal &P dari &N');
            },
        ];
    }
}
