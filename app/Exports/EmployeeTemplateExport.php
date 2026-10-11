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
            // Identitas Utama
            'NIK*',
            'NAMA*',
            'JENIS KELAMIN',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'AGAMA',
            'SUSUNAN KELUARGA',

            // Info Jabatan & Formasi
            'STATUS KARYAWAN*',
            'JABATAN*',
            'LEVEL*',
            'UNIT KERJA*',
            'GOLONGAN',
            'RM LEVEL',
            'STATUS PENUGASAN',

            // Masa Kerja & Karir
            'TMT BEKERJA',
            'TANGGAL DIANGKAT STAF',
            'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA',
            'JALUR MASUK',
            'JOB GRADE',
            'PERSON GRADE',

            // Lain-lain
            'PENDIDIKAN TERAKHIR',
            'SEKOLAH',
            'TANGGAL MBT',
            'TANGGAL PENSIUN',
        ];
    }

    public function array(): array
    {
        return [
            [
                // Identitas Utama
                '13009988',
                'BUDI SANTOSO',
                'L',
                'Pontianak',
                '05/05/1990',
                'Islam',
                'K/1',

                // Info Jabatan & Formasi
                'PKWTT',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                '',
                'Definitif',

                // Masa Kerja & Karir
                '01/01/2018',
                '01/06/2020',
                '01/01/2022',
                '05/01/2022',
                'Reguler',
                '11',
                '11',

                // Lain-lain
                'D4 / S1',
                'INSTIPER Yogyakarta',
                '01/05/2045',
                '01/05/2046',
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'E' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_lahir
            'O' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tmt_bekerja
            'P' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_diangkat_staf
            'Q' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_dalam_jabatan
            'R' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tmt_unit_kerja
            'X' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_mbt
            'Y' => NumberFormat::FORMAT_DATE_DDMMYYYY, // tanggal_pensiun
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // Add helpful comments for date fields & options
                $comments = [
                    'A1' => 'Format 8 digit angka. Contoh: 13009988',
                    'C1' => 'Pilihan: L (Laki-laki) atau P (Perempuan)',
                    'E1' => 'Format: dd/mm/yyyy. Contoh: 01/01/1990',
                    'O1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2018',
                    'P1' => 'Format: dd/mm/yyyy. Kosongkan jika Karpel',
                    'Q1' => 'Format: dd/mm/yyyy. Contoh: 01/01/2022',
                    'R1' => 'Format: dd/mm/yyyy. Contoh: 05/01/2022',
                    'X1' => 'Format: dd/mm/yyyy. Contoh: 01/05/2045',
                    'Y1' => 'Format: dd/mm/yyyy. Contoh: 01/05/2046',
                ];

                foreach ($comments as $cell => $text) {
                    $sheet->getComment($cell)->getText()->createTextRun($text);
                }

                foreach (range('A', 'Y') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getStyle('A1:Y1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                ]);
            },
        ];
    }
}
