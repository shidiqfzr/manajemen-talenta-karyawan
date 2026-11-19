<?php

namespace App\Exports;

use App\Models\User;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Ekspor rincian personil satu sel matriks MPP ke Excel. Memuat NIK dan nama (UU PDP No. 27/2022).
 */
class MppPersonnelExport implements FromArray, WithColumnFormatting, WithEvents, WithHeadings, WithProperties, WithTitle
{
    /**
     * @param  array<int, array<string, mixed>>  $employees  Baris rincian personil dari drilldown MPP.
     * @param  string  $cellLabel  Mis. "RM-1 TAN — Realisasi per Nov 2026".
     */
    public function __construct(
        private readonly array $employees,
        private readonly string $cellLabel,
        private readonly ?User $user = null,
    ) {}

    public function title(): string
    {
        return 'Rincian Personil';
    }

    public function properties(): array
    {
        $userName = $this->user?->name ?? 'Administrator HRIS';
        $userNik = $this->user?->nik ?? 'ADMIN';

        return [
            'creator' => "{$userName} ({$userNik})",
            'lastModifiedBy' => 'PTPN IV Regional V HRIS System',
            'title' => 'Rincian Personil MPP — '.$this->cellLabel,
            'description' => 'Dokumen Rahasia Data Karyawan diekspor sesuai UU PDP No. 27/2022',
            'subject' => 'Man Power Planning',
            'keywords' => 'ptpn4, hris, mpp, personil, confidential',
            'category' => 'HR Confidential Document',
            'manager' => 'Bagian SDM & TI PTPN IV Regional V',
            'company' => 'PT Perkebunan Nusantara IV (Persero) Regional V',
        ];
    }

    public function headings(): array
    {
        return ['No.', 'NIK', 'NAMA LENGKAP', 'JABATAN', 'UNIT KERJA', 'GOLONGAN', 'TANGGAL PENEMPATAN KARPIM', 'TANGGAL MBT', 'TANGGAL PENSIUN', 'STATUS'];
    }

    public function array(): array
    {
        $rows = [];
        foreach (array_values($this->employees) as $i => $emp) {
            $rows[] = [
                $i + 1,
                $this->clean($emp['nik'] ?? ''),
                $this->clean($emp['nama'] ?? ''),
                $this->clean($emp['jabatan'] ?? ''),
                $this->clean($emp['unit_kerja'] ?? ''),
                $this->clean($emp['golongan'] ?? '—'),
                $this->clean($emp['tanggal_penempatan'] ?? '—'),
                $this->clean($emp['tanggal_mbt'] ?? ''),
                $this->clean($emp['tanggal_pensiun'] ?? ''),
                $this->clean($emp['status_karyawan'] ?? ''),
            ];
        }

        return $rows;
    }

    /** NIK tetap teks agar leading zero tidak hilang. */
    public function columnFormats(): array
    {
        return ['B' => NumberFormat::FORMAT_TEXT];
    }

    /** Cegah formula injection pada sel yang berasal dari data karyawan. */
    private function clean(mixed $value): string
    {
        $str = (string) ($value ?? '');

        return $str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$str : $str;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->freezePane('A2');
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->getStyle('A1:J1')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B5330']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']]],
                ]);
                foreach (range('A', 'J') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }
                $sheet->setAutoFilter('A1:J1');

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0)
                    ->setRowsToRepeatAtTopByStartAndEnd(1, 1);

                $userName = $this->user?->name ?? 'Admin SDM';
                $sheet->getHeaderFooter()
                    ->setOddHeader('&L&8'.$this->cellLabel)
                    ->setOddFooter('&L&8Dokumen Rahasia PT Perkebunan Nusantara IV Regional V - UU PDP No. 27/2022&R&8Diunduh oleh: '.$userName.' (&D &T) - Hal &P dari &N');
            },
        ];
    }
}
