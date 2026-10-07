<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EmployeeTemplateExport implements FromArray, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Template Data Karyawan';
    }

    public function headings(): array
    {
        return [
            'NIK',
            'NAMA',
            'JABATAN',
            'LEVEL',
            'UNIT KERJA',
            'GOLONGAN 2024',
            'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'JENIS KELAMIN',
            'TMT BEKERJA',
            'TANGGAL DIANGKAT STAF',
            'SUSUNAN KELUARGA',
            'JOB GRADER',
            'PERSON GRADE',
            'TANGGAL MBT',
            'TANGGAL PENSIUN',
            'AGAMA',
            'PENDIDIKAN TERAKHIR',
            'SEKOLAH',
        ];
    }

    public function array(): array
    {
        return [
            [
                '13009988',
                'BUDI SANTOSO',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                '01/01/2022',
                '05/01/2022',
                'Pontianak',
                '05/05/1990',
                'L',
                '01/01/2018',
                '01/06/2020',
                'K/1',
                '11',
                '11',
                '01/05/2045',
                '01/05/2046',
                'Islam',
                'D4 / S1',
                'INSTIPER Yogyakarta',
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_dalam_jabatan
            'H' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tmt_unit_kerja
            'J' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_lahir
            'L' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tmt_bekerja
            'M' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_diangkat_staf
            'Q' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_mbt
            'R' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_pensiun
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Add helpful comments for date fields & options
                $comments = [
                    'G1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'H1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'J1' => 'Format: dd/mm/yyyy. Contoh: 01/01/1990',
                    'K1' => 'Pilihan: L (Laki-laki) atau P (Perempuan)',
                    'L1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2018',
                    'M1' => 'Format: dd/mm/yyyy. Kosongkan jika Karpel',
                    'Q1' => 'Format: dd/mm/yyyy. Contoh: 01/05/2045',
                    'R1' => 'Format: dd/mm/yyyy. Contoh: 01/05/2046',
                ];

                foreach ($comments as $cell => $text) {
                    $sheet->getComment($cell)->getText()->createTextRun($text);
                }

                foreach (range('A', 'U') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getStyle('A1:U1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            },
        ];
    }
}
