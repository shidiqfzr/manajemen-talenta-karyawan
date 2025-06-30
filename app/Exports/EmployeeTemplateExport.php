<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EmployeeTemplateExport implements FromArray, WithHeadings, WithColumnFormatting, WithEvents, WithTitle
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
                '1234567890',
                'John Doe',
                'Staff',
                'IIIa',
                'IT Dept',
                'A1',
                '01/01/2022',
                '05/01/2022',
                'Jakarta',
                '05/05/1995',
                '01/01/2020',
                '12/01/2020',
                'Menikah',
                'JG1',
                'PG2',
                '08/01/2023',
                '05/01/2025',
                'Islam',
                'S1 Teknik',
                'Universitas Indonesia',
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'G' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_dalam_jabatan
            'H' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tmt_unit_kerja
            'J' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_lahir
            'K' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tmt_bekerja
            'L' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_diangkat_staf
            'P' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_mbt
            'Q' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_pensiun
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Add helpful comments for date fields
                $comments = [
                    'G1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'H1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'J1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'K1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'L1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'P1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'Q1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                ];

                foreach ($comments as $cell => $text) {
                    $sheet->getComment($cell)->getText()->createTextRun($text);
                }

                foreach (range('A', 'T') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getStyle('A1:T1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            },
        ];
    }
}
