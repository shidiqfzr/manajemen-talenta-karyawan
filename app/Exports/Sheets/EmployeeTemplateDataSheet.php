<?php

namespace App\Exports\Sheets;

use App\Models\Unit;
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

            // Info Jabatan & Formasi (Kolom H - M). Strata (Karpim/Karpel) dan RM Band tidak diminta:
            // keduanya diturunkan dari Formasi Jabatan pada Master Formasi.
            'STATUS KARYAWAN*',
            'UNIT KERJA*',
            'JABATAN*',
            'GOLONGAN',
            'STATUS PENUGASAN',
            'TUGAS RANGKAP / PENUGASAN SEMENTARA',

            // Masa Kerja & Karir (Kolom N - T)
            'TMT BEKERJA',
            'TANGGAL DIANGKAT STAF',
            'TANGGAL DALAM JABATAN',
            'TMT UNIT KERJA',
            'JALUR MASUK',
            'JOB GRADE',
            'PERSON GRADE',

            // Lain-lain / Pendidikan & Pensiun (Kolom U - X)
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

                // Info Jabatan & Formasi (Kolom H - M)
                'Tetap (PKWTT)',
                'Kebun Inti Gunung Meliau',
                'Asisten Afdeling',
                'IIIA/00',
                'Definitif',
                '', // Catatan tugas rangkap / penugasan sementara (opsional)

                // Masa Kerja & Karir (Kolom N - T)
                '2018-01-01',
                '2020-06-01',
                '2022-01-01',
                '2022-01-05',
                'Reguler',
                11,
                11,

                // Lain-lain / Pendidikan & Pensiun (Kolom U - X)
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
            'N' => NumberFormat::FORMAT_DATE_YYYYMMDD, // TMT Bekerja
            'O' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Diangkat Staf
            'P' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Dalam Jabatan
            'Q' => NumberFormat::FORMAT_DATE_YYYYMMDD, // TMT Unit Kerja
            'W' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal MBT
            'X' => NumberFormat::FORMAT_DATE_YYYYMMDD, // Tanggal Pensiun
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

                // 3. Styling Header Seragam: Hijau Korporat PTPN IV (Primary 800 - #1B5330) untuk seluruh 24 Kolom
                $sheet->getStyle('A1:X1')->applyFromArray([
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
                $mandatoryCells = ['A1', 'B1', 'H1', 'I1', 'J1'];
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
                        'title' => 'Panduan Unit Kerja (Wajib)',
                        'prompt' => 'Pilih Unit Kerja resmi lebih dulu. Daftar Jabatan di kolom sebelah menyesuaikan unit yang dipilih.',
                    ],
                    'J1' => [
                        'title' => 'Panduan Jabatan (Wajib)',
                        'prompt' => 'Pilih setelah Unit Kerja. Hanya Formasi Jabatan pada unit tersebut yang tersedia; lihat sheet Formasi per Unit untuk RM Band, bidang, dan sisa kuota.',
                    ],
                    'K1' => [
                        'title' => 'Panduan Golongan',
                        'prompt' => 'Format baku: [Gol]/[Berkala]. Contoh: IIIA/00, IIIB/02, IVA/05.',
                    ],
                    'L1' => [
                        'title' => 'Panduan Status Penugasan',
                        'prompt' => 'Pilih status penugasan: Definitif, Diperbantukan, Pejabat Sementara (Pjs), Tugas Belajar, atau Advisor.',
                    ],
                    'M1' => [
                        'title' => 'Panduan Tugas Rangkap',
                        'prompt' => 'Opsional. Catatan penugasan sementara atau jabatan rangkap (jika ada). Pengelolaan dinas resmi melalui modul Mutasi.',
                    ],
                    'N1' => [
                        'title' => 'Panduan TMT Bekerja',
                        'prompt' => 'Wajib ada jika Karpim. Minimal 18 tahun setelah Tanggal Lahir.',
                    ],
                    'O1' => [
                        'title' => 'Panduan Tgl Diangkat Staf',
                        'prompt' => 'Kosongkan jika Karpel. Untuk Karpim minimal sama dengan TMT Bekerja.',
                    ],
                    'S1' => [
                        'title' => 'Panduan Job Grade',
                        'prompt' => 'Rentang bobot jabatan: Karpel (1-10), Karpim (11-16).',
                    ],
                    'T1' => [
                        'title' => 'Panduan Person Grade',
                        'prompt' => 'Rentang person grade: Karpel (1-10), Karpim (11-16).',
                    ],
                    'W1' => [
                        'title' => 'Panduan Tanggal MBT',
                        'prompt' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Masa Bebas Tugas (55 tahun).',
                    ],
                    'X1' => [
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

                // 6. Auto-sizing untuk seluruh kolom A s.d. X
                foreach (range('A', 'X') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 7. Aktifkan fitur AutoFilter bawaan Excel pada baris header (A1:X1)
                $sheet->setAutoFilter('A1:X1');

                // 8. Wrap text aktif untuk kolom M (Tugas Rangkap)
                $sheet->getStyle('M')->getAlignment()->setWrapText(true);

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
                $channelVal = $createValidation('"CKP, Talent Scouting, RBB, Reguler"');

                // Dropdown formula merujuk ke Sheet 'Referensi Master Data'
                $unitLast = max(2, Unit::active()->count() + 1);
                $unitVal = $createValidation("'Referensi Master Data'!\$A\$2:\$A\${$unitLast}");
                $unitVal->setErrorStyle(DataValidation::STYLE_STOP);
                $unitVal->setErrorTitle('Unit Kerja tidak dikenal');
                $unitVal->setError('Pilih Unit Kerja resmi dari daftar pilihan. Nama unit harus sama dengan Master Formasi.');

                // Jabatan bergantung pada Unit Kerja (kolom I): daftar diambil dari baris unit tersebut pada sheet 'Formasi per Unit'.
                $formasiSheet = new EmployeeTemplateFormasiSheet;
                $positionValidation = function (int $row) use ($createValidation, $formasiSheet) {
                    $validation = $createValidation($formasiSheet->positionListFormula($row));
                    $validation->setErrorStyle(DataValidation::STYLE_STOP);
                    $validation->setErrorTitle('Jabatan tidak sesuai Unit Kerja');
                    $validation->setError('Pilih Unit Kerja lebih dulu, lalu pilih Jabatan dari daftar. Hanya Formasi Jabatan pada unit tersebut yang tersedia.');

                    return $validation;
                };

                $employmentVal = $createValidation('\'Referensi Master Data\'!$D$2:$D$4');
                $assignmentVal = $createValidation('\'Referensi Master Data\'!$E$2:$E$6');
                $religionVal = $createValidation('\'Referensi Master Data\'!$G$2:$G$7');
                $familyVal = $createValidation('\'Referensi Master Data\'!$H$2:$H$7');
                $educationVal = $createValidation('\'Referensi Master Data\'!$I$2:$I$9');

                for ($r = 2; $r <= 250; $r++) {
                    $sheet->getCell("C{$r}")->setDataValidation(clone $genderVal);
                    $sheet->getCell("F{$r}")->setDataValidation(clone $religionVal);
                    $sheet->getCell("G{$r}")->setDataValidation(clone $familyVal);
                    $sheet->getCell("H{$r}")->setDataValidation(clone $employmentVal);
                    $sheet->getCell("I{$r}")->setDataValidation(clone $unitVal);
                    $sheet->getCell("J{$r}")->setDataValidation($positionValidation($r));
                    $sheet->getCell("L{$r}")->setDataValidation(clone $assignmentVal);
                    $sheet->getCell("R{$r}")->setDataValidation(clone $channelVal);
                    $sheet->getCell("U{$r}")->setDataValidation(clone $educationVal);
                }
            },
        ];
    }
}
