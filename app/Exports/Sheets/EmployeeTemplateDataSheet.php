<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EmployeeTemplateDataSheet implements FromArray, WithColumnFormatting, WithEvents, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Data Karyawan';
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
                '1990-05-15',
                'Islam',
                'K/1',

                // Info Jabatan & Formasi
                'Tetap (PKWTT)',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',

                // Masa Kerja & Karir
                '2018-01-01',
                '2020-06-01',
                '2022-01-01',
                '2022-01-05',
                'Reguler',
                11,
                11,

                // Lain-lain
                'D4 / S1',
                'INSTIPER Yogyakarta',
                '', // Kosongkan agar dihitung otomatis oleh sistem
                '', // Kosongkan agar dihitung otomatis oleh sistem
            ],
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,         // NIK strictly text
            'E' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Lahir
            'O' => NumberFormat::FORMAT_DATE_YYYYMMDD, // TMT Bekerja
            'P' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Diangkat Staf
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Dalam Jabatan
            'R' => NumberFormat::FORMAT_DATE_YYYYMMDD, // TMT Unit Kerja
            'X' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal MBT
            'Y' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Pensiun
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Tooltips / Comments pada Header
                $comments = [
                    'A1' => 'Wajib diisi. Format 8 digit angka (contoh: 13009988). Sel diformat teks agar nol di depan tidak hilang.',
                    'B1' => 'Wajib diisi. Nama lengkap pegawai (akan otomatis distandarkan menjadi HURUF BESAR).',
                    'C1' => 'Pilihan: L (Laki-laki) atau P (Perempuan). Tersedia dropdown pilihan.',
                    'E1' => 'Format tanggal: YYYY-MM-DD atau DD/MM/YYYY. Dasar perhitungan Pensiun & MBT.',
                    'F1' => 'Pilih salah satu dari 6 agama resmi pada dropdown.',
                    'G1' => 'Pilih status PTKP pada dropdown (L, TK, K/0, K/1, K/2, K/3).',
                    'H1' => 'Wajib diisi. Pilih: Tetap (PKWTT), Kontrak (PKWT), atau Calon Pegawai.',
                    'I1' => 'Wajib diisi. Nomenklatur jabatan harus sesuai dengan Unit Kerja & Strata.',
                    'J1' => 'Wajib diisi. Pilih: Karpim atau Karpel.',
                    'K1' => 'Wajib diisi. Pilih salah satu dari 43 Unit Kerja resmi pada dropdown.',
                    'L1' => 'Format baku: [Gol]/[Berkala]. Contoh: IIIA/00, IIIB/02, IVA/05.',
                    'M1' => 'Eselon Formasi: RM-1, RM-2, RM-3 (Karpim) atau RM-4 (Karpel).',
                    'N1' => 'Pilih status penugasan: Definitif, Diperbantukan, Pejabat Sementara (Pjs), Tugas Belajar, atau Advisor.',
                    'O1' => 'Wajib ada jika Karpim. Minimal 18 tahun setelah Tanggal Lahir.',
                    'P1' => 'Kosongkan jika Karpel. Untuk Karpim minimal sama dengan TMT Bekerja.',
                    'T1' => 'Rentang bobot jabatan: Karpel (1-10), Karpim (11-16).',
                    'U1' => 'Rentang person grade: Karpel (1-10), Karpim (11-16).',
                    'X1' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Masa Bebas Tugas (55 tahun).',
                    'Y1' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Batas Usia Pensiun (56 tahun).',
                ];

                foreach ($comments as $cell => $text) {
                    $sheet->getComment($cell)->getText()->createTextRun($text);
                }

                // 2. Styling Header: Kolom Wajib (Emerald) vs Kolom Opsional (Slate)
                $mandatoryCols = ['A1', 'B1', 'H1', 'I1', 'J1', 'K1'];
                foreach (range('A', 'Y') as $col) {
                    $cell = $col.'1';
                    $isMandatory = in_array($cell, $mandatoryCols, true);

                    $bgColor = $isMandatory ? '2F8250' : '334155'; // Emerald PTPN vs Slate

                    $sheet->getStyle($cell)->applyFromArray([
                        'font' => [
                            'bold' => true,
                            'color' => ['rgb' => 'FFFFFF'],
                            'size' => 10,
                        ],
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $bgColor],
                        ],
                        'alignment' => [
                            'horizontal' => Alignment::HORIZONTAL_CENTER,
                            'vertical' => Alignment::VERTICAL_CENTER,
                            'wrapText' => true,
                        ],
                    ]);

                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $sheet->getRowDimension(1)->setRowHeight(28);

                // 3. Pasang In-Cell Data Validation (Dropdowns) untuk baris 2 sampai 250
                $createValidation = function (string $formula) {
                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(true);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Pilihan Kategori');
                    $validation->setError('Silakan pilih salah satu opsi dari daftar pilihan yang tersedia.');
                    $validation->setFormula1($formula);

                    return $validation;
                };

                // Dropdown literal sederhana
                $genderVal = $createValidation('"L, P"');
                $levelVal = $createValidation('"Karpim, Karpel"');
                $rmLevelVal = $createValidation('"RM-1, RM-2, RM-3, RM-4"');
                $channelVal = $createValidation('"CKP, Talent Scouting, RBB, Reguler"');

                // Dropdown formula merujuk ke Sheet 'Referensi Master Data'
                $unitVal = $createValidation('\'Referensi Master Data\'!$A$2:$A$50');
                $positionVal = $createValidation('\'Referensi Master Data\'!$B$2:$B$150');
                $employmentVal = $createValidation('\'Referensi Master Data\'!$E$2:$E$4');
                $assignmentVal = $createValidation('\'Referensi Master Data\'!$F$2:$F$6');
                $religionVal = $createValidation('\'Referensi Master Data\'!$H$2:$H$7');
                $familyVal = $createValidation('\'Referensi Master Data\'!$I$2:$I$7');
                $educationVal = $createValidation('\'Referensi Master Data\'!$J$2:$J$9');

                for ($r = 2; $r <= 250; $r++) {
                    $sheet->getCell("C{$r}")->setDataValidation(clone $genderVal);
                    $sheet->getCell("F{$r}")->setDataValidation(clone $religionVal);
                    $sheet->getCell("G{$r}")->setDataValidation(clone $familyVal);
                    $sheet->getCell("H{$r}")->setDataValidation(clone $employmentVal);
                    $sheet->getCell("I{$r}")->setDataValidation(clone $positionVal);
                    $sheet->getCell("J{$r}")->setDataValidation(clone $levelVal);
                    $sheet->getCell("K{$r}")->setDataValidation(clone $unitVal);
                    $sheet->getCell("M{$r}")->setDataValidation(clone $rmLevelVal);
                    $sheet->getCell("N{$r}")->setDataValidation(clone $assignmentVal);
                    $sheet->getCell("S{$r}")->setDataValidation(clone $channelVal);
                    $sheet->getCell("V{$r}")->setDataValidation(clone $educationVal);
                }
            },
        ];
    }
}
