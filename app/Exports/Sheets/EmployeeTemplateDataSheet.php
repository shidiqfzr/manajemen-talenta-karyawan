<?php

namespace App\Exports\Sheets;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Color;
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
            // Identitas Utama (Kolom A - G)
            'NIK*',
            'NAMA*',
            'JENIS KELAMIN',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'AGAMA',
            'SUSUNAN KELUARGA',

            // Info Jabatan & Formasi (Kolom H - O)
            'STATUS KARYAWAN*',
            'JABATAN*',
            'LEVEL*',
            'UNIT KERJA*',
            'GOLONGAN',
            'RM LEVEL',
            'STATUS PENUGASAN',
            'TUGAS RANGKAP / PENUGASAN SEMENTARA',

            // Masa Kerja & Karir (Kolom P - V)
            'TMT BEKERJA',
            'TANGGAL DIANGKAT STAF',
            'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA',
            'JALUR MASUK',
            'JOB GRADE',
            'PERSON GRADE',

            // Lain-lain / Pendidikan & Pensiun (Kolom W - Z)
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
                // Identitas Utama (Kolom A - G)
                '13009988',
                'BUDI SANTOSO',
                'L',
                'Pontianak',
                '1990-05-15',
                'Islam',
                'K/1',

                // Info Jabatan & Formasi (Kolom H - O)
                'Tetap (PKWTT)',
                'Asisten Afdeling',
                'Karpim',
                'Kebun Inti Gunung Meliau',
                'IIIA/00',
                'RM-3',
                'Definitif',
                '', // Catatan tugas rangkap / penugasan sementara (opsional)

                // Masa Kerja & Karir (Kolom P - V)
                '2018-01-01',
                '2020-06-01',
                '2022-01-01',
                '2022-01-05',
                'Reguler',
                11,
                11,

                // Lain-lain / Pendidikan & Pensiun (Kolom W - Z)
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
            'P' => NumberFormat::FORMAT_DATE_YYYYMMDD, // TMT Bekerja
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Diangkat Staf
            'R' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Dalam Jabatan
            'S' => NumberFormat::FORMAT_DATE_YYYYMMDD, // TMT Unit Kerja
            'Y' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal MBT
            'Z' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Pensiun
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Freeze baris pertama agar header selalu terlihat saat scrolling
                $sheet->freezePane('A2');

                // 2. Tinggi baris header
                $sheet->getRowDimension(1)->setRowHeight(28);

                // 3. Styling Header Seragam: Hijau Korporat PTPN IV (Primary 800 - #1B5330) untuk seluruh 26 Kolom
                $sheet->getStyle('A1:Z1')->applyFromArray([
                    'font' => [
                        'bold' => true,
                        'color' => ['rgb' => 'FFFFFF'],
                        'size' => 10,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => '1B5330'],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'CBD5E1'],
                        ],
                    ],
                ]);

                // 4. Beri warna merah (#EF4444) khusus untuk karakter tanda bintang (*) pada kolom wajib
                $mandatoryCells = ['A1', 'B1', 'H1', 'I1', 'J1', 'K1'];
                foreach ($mandatoryCells as $cellId) {
                    $cell = $sheet->getCell($cellId);
                    $val = (string) $cell->getValue();
                    $label = trim(str_replace('*', '', $val));

                    $richText = new RichText;

                    $titleRun = $richText->createTextRun($label.' ');
                    $titleRun->getFont()->setBold(true)->setColor(new Color('FFFFFF'))->setSize(10);

                    $starRun = $richText->createTextRun('*');
                    $starRun->getFont()->setBold(true)->setColor(new Color('EF4444'))->setSize(10);

                    $cell->setValue($richText);
                }

                // 5. Panduan Input Message pada Header (Bersih, Melayang saat Sel Diklik, 100% Bebas Garis Tembus)
                $inputGuides = [
                    'A1' => [
                        'title' => 'Panduan NIK (Wajib)',
                        'prompt' => 'Format 8 digit angka (contoh: 13009988). Sel diformat teks agar nol di depan tidak hilang.',
                    ],
                    'B1' => [
                        'title' => 'Panduan Nama Pegawai (Wajib)',
                        'prompt' => 'Nama lengkap pegawai (akan otomatis distandarkan menjadi HURUF BESAR oleh sistem).',
                    ],
                    'C1' => [
                        'title' => 'Panduan Jenis Kelamin',
                        'prompt' => 'Pilihan: L (Laki-laki) atau P (Perempuan). Tersedia dropdown pilihan.',
                    ],
                    'E1' => [
                        'title' => 'Panduan Tanggal Lahir',
                        'prompt' => 'Format tanggal: YYYY-MM-DD atau DD/MM/YYYY. Dasar perhitungan Pensiun & MBT.',
                    ],
                    'F1' => [
                        'title' => 'Panduan Agama',
                        'prompt' => 'Pilih salah satu dari 6 agama resmi pada dropdown.',
                    ],
                    'G1' => [
                        'title' => 'Panduan Susunan Keluarga',
                        'prompt' => 'Pilih status PTKP pada dropdown (L, TK, K/0, K/1, K/2, K/3).',
                    ],
                    'H1' => [
                        'title' => 'Panduan Status Karyawan (Wajib)',
                        'prompt' => 'Pilih: Tetap (PKWTT), Kontrak (PKWT), atau Calon Pegawai.',
                    ],
                    'I1' => [
                        'title' => 'Panduan Jabatan (Wajib)',
                        'prompt' => 'Nomenklatur jabatan harus sesuai dengan Unit Kerja & Strata.',
                    ],
                    'J1' => [
                        'title' => 'Panduan Level Strata (Wajib)',
                        'prompt' => 'Pilih: Karpim atau Karpel.',
                    ],
                    'K1' => [
                        'title' => 'Panduan Unit Kerja (Wajib)',
                        'prompt' => 'Pilih salah satu dari 43 Unit Kerja resmi pada dropdown.',
                    ],
                    'L1' => [
                        'title' => 'Panduan Golongan',
                        'prompt' => 'Format baku: [Gol]/[Berkala]. Contoh: IIIA/00, IIIB/02, IVA/05.',
                    ],
                    'M1' => [
                        'title' => 'Panduan Eselon Formasi / RM Level',
                        'prompt' => 'Eselon Formasi: RM-1, RM-2, RM-3 (Karpim) atau RM-4 (Karpel).',
                    ],
                    'N1' => [
                        'title' => 'Panduan Status Penugasan',
                        'prompt' => 'Pilih status penugasan: Definitif, Diperbantukan, Pejabat Sementara (Pjs), Tugas Belajar, atau Advisor.',
                    ],
                    'O1' => [
                        'title' => 'Panduan Tugas Rangkap',
                        'prompt' => 'Opsional. Catatan penugasan sementara atau jabatan rangkap (jika ada). Pengelolaan dinas resmi melalui modul Mutasi.',
                    ],
                    'P1' => [
                        'title' => 'Panduan TMT Bekerja',
                        'prompt' => 'Wajib ada jika Karpim. Minimal 18 tahun setelah Tanggal Lahir.',
                    ],
                    'Q1' => [
                        'title' => 'Panduan Tgl Diangkat Staf',
                        'prompt' => 'Kosongkan jika Karpel. Untuk Karpim minimal sama dengan TMT Bekerja.',
                    ],
                    'U1' => [
                        'title' => 'Panduan Job Grade',
                        'prompt' => 'Rentang bobot jabatan: Karpel (1-10), Karpim (11-16).',
                    ],
                    'V1' => [
                        'title' => 'Panduan Person Grade',
                        'prompt' => 'Rentang person grade: Karpel (1-10), Karpim (11-16).',
                    ],
                    'Y1' => [
                        'title' => 'Panduan Tanggal MBT',
                        'prompt' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Masa Bebas Tugas (55 tahun).',
                    ],
                    'Z1' => [
                        'title' => 'Panduan Tanggal Pensiun',
                        'prompt' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Batas Usia Pensiun (56 tahun).',
                    ],
                ];

                foreach ($inputGuides as $cell => $guide) {
                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_NONE);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(false);
                    $validation->setPromptTitle($guide['title']);
                    $validation->setPrompt($guide['prompt']);
                    $sheet->getCell($cell)->setDataValidation($validation);
                }

                // 6. Auto-sizing untuk seluruh kolom A s.d. Z
                foreach (range('A', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 7. Aktifkan fitur AutoFilter bawaan Excel pada baris header (A1:Z1)
                $sheet->setAutoFilter('A1:Z1');

                // 8. Wrap text aktif untuk kolom O (Tugas Rangkap)
                $sheet->getStyle('O')->getAlignment()->setWrapText(true);

                // 9. Pasang In-Cell Data Validation (Dropdowns) untuk baris 2 sampai 250
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
                    $sheet->getCell("T{$r}")->setDataValidation(clone $channelVal);
                    $sheet->getCell("W{$r}")->setDataValidation(clone $educationVal);
                }
            },
        ];
    }
}
