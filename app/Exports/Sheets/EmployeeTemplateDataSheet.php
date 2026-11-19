<?php

namespace App\Exports\Sheets;

use App\Models\Employee;
use App\Models\Unit;
use App\Services\Import\EmployeeImportColumns;
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

    /**
     * Judul kolom dari satu daftar kolom bersama (EmployeeImportColumns). Strata (Karpim/Karpel) dan RM Band tidak diminta:
     * keduanya diturunkan dari Formasi Jabatan pada Master Formasi.
     */
    public function headings(): array
    {
        return EmployeeImportColumns::headings(EmployeeImportColumns::LAYOUT_TEMPLATE, true);
    }

    public function array(): array
    {
        return [
            [
                // Identitas Utama (Kolom A - G)
                '13009988',
                'BUDI SANTOSO',
                'Laki-laki',
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
        // NIK harus teks (nol di depan tidak boleh hilang); kolom tanggal berformat ISO. Hurufnya dari daftar kolom bersama.
        $formats = [EmployeeImportColumns::letter('nik') => NumberFormat::FORMAT_TEXT];
        foreach (EmployeeImportColumns::fieldsOfType(EmployeeImportColumns::TYPE_DATE) as $field) {
            $formats[EmployeeImportColumns::letter($field)] = NumberFormat::FORMAT_DATE_YYYYMMDD;
        }

        return $formats;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $letter = EmployeeImportColumns::letter(...);
                $lastColumn = EmployeeImportColumns::lastLetter();

                // 1. Freeze baris pertama agar header selalu terlihat saat scrolling
                $sheet->freezePane('A2');

                // 2. Tinggi baris header
                $sheet->getRowDimension(1)->setRowHeight(28);

                // 3. Styling Header Seragam: Hijau Korporat PTPN IV (Primary 800 - #1B5330) untuk seluruh 24 Kolom
                $sheet->getStyle("A1:{$lastColumn}1")->applyFromArray([
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
                $mandatoryCells = array_map(fn (string $field): string => $letter($field).'1', EmployeeImportColumns::requiredFields());
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
                    'nik' => [
                        'title' => 'Panduan NIK (Wajib)',
                        'prompt' => 'Format 8 digit angka (contoh: 13009988). Sel diformat teks agar nol di depan tidak hilang.',
                    ],
                    'nama' => [
                        'title' => 'Panduan Nama Karyawan (Wajib)',
                        'prompt' => 'Nama lengkap karyawan (akan otomatis distandarkan menjadi HURUF BESAR oleh sistem).',
                    ],
                    'jenis_kelamin' => [
                        'title' => 'Panduan Jenis Kelamin',
                        'prompt' => 'Pilih Laki-laki atau Perempuan dari dropdown.',
                    ],
                    'tanggal_lahir' => [
                        'title' => 'Panduan Tanggal Lahir',
                        'prompt' => 'Format tanggal: YYYY-MM-DD atau DD/MM/YYYY. Dasar perhitungan Pensiun & MBT.',
                    ],
                    'agama' => [
                        'title' => 'Panduan Agama',
                        'prompt' => 'Pilih salah satu dari 6 agama resmi pada dropdown.',
                    ],
                    'susunan_keluarga' => [
                        'title' => 'Panduan Susunan Keluarga',
                        'prompt' => 'Pilih status PTKP pada dropdown (L, TK, K/0, K/1, K/2, K/3).',
                    ],
                    'status_karyawan' => [
                        'title' => 'Panduan Status Karyawan (Wajib)',
                        'prompt' => 'Pilih: Tetap (PKWTT), Kontrak (PKWT), atau Calon Pegawai.',
                    ],
                    'unit_kerja' => [
                        'title' => 'Panduan Unit Kerja (Wajib)',
                        'prompt' => 'Pilih Unit Kerja resmi lebih dulu. Daftar Jabatan di kolom sebelah menyesuaikan unit yang dipilih.',
                    ],
                    'jabatan' => [
                        'title' => 'Panduan Jabatan (Wajib)',
                        'prompt' => 'Pilih setelah Unit Kerja; hanya Formasi Jabatan pada unit itu yang tersedia (RM Band, bidang, kuota: sheet Formasi per Unit). Advisor/Diperbantukan: pilih jabatan penampungannya, mis. Advisor RM-3, bila sudah dibuka pada unit.',
                    ],
                    'golongan' => [
                        'title' => 'Panduan Golongan',
                        'prompt' => 'Format baku: [Gol]/[Berkala]. Contoh: IIIA/00, IIIB/02, IVA/05.',
                    ],
                    'status_penugasan' => [
                        'title' => 'Panduan Status Jabatan Utama',
                        'prompt' => 'Status untuk jabatan pada kolom Jabatan (bukan untuk Tugas Rangkap). Pilih Definitif, Pejabat Sementara (Pjs), atau Tugas Belajar; kosong dianggap Definitif. Plt/Pjs tugas rangkap ditulis pada kolom Tugas Rangkap.',
                    ],
                    'tugas_rangkap_penugasan_sementara' => [
                        'title' => 'Panduan Tugas Rangkap',
                        'prompt' => 'Opsional. Satu tugas per baris (Alt+Enter): Plt. Manajer PKS Gunung Meliau TMT 15 Juli 2026, atau [Plt] Jabatan — Unit (TMT: 2026-07-15). Tulis unit bila jabatan ada di unit lain. Jabatan dan unit harus sesuai master, bila tidak baris ditolak.',
                    ],
                    'tmt_bekerja' => [
                        'title' => 'Panduan TMT Bekerja',
                        'prompt' => 'Wajib ada jika Karpim. Minimal 18 tahun setelah Tanggal Lahir.',
                    ],
                    'tanggal_diangkat_staf' => [
                        'title' => 'Panduan Tgl Diangkat Staf',
                        'prompt' => 'Kosongkan jika Karpel. Untuk Karpim minimal sama dengan TMT Bekerja.',
                    ],
                    'job_grade' => [
                        'title' => 'Panduan Job Grade',
                        'prompt' => 'Rentang bobot jabatan: Karpel (1-10), Karpim (11-16).',
                    ],
                    'person_grade' => [
                        'title' => 'Panduan Person Grade',
                        'prompt' => 'Rentang person grade: Karpel (1-10), Karpim (11-16).',
                    ],
                    'tanggal_mbt' => [
                        'title' => 'Panduan Tanggal MBT',
                        'prompt' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Masa Bebas Tugas (6 bulan sebelum pensiun).',
                    ],
                    'tanggal_pensiun' => [
                        'title' => 'Panduan Tanggal Pensiun',
                        'prompt' => 'Boleh dikosongkan! Sistem akan otomatis menghitung Batas Usia Pensiun (56 tahun).',
                    ],
                ];

                foreach ($inputGuides as $field => $guide) {
                    $cell = $letter($field).'1';
                    $validation = new DataValidation;
                    $validation->setType(DataValidation::TYPE_NONE);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(false);
                    $validation->setPromptTitle($guide['title']);
                    $validation->setPrompt($guide['prompt']);
                    $sheet->getCell($cell)->setDataValidation($validation);
                }

                // 6. Auto-sizing untuk seluruh kolom A s.d. X
                foreach (EmployeeImportColumns::letters() as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 7. Aktifkan fitur AutoFilter bawaan Excel pada baris header (A1:X1)
                $sheet->setAutoFilter("A1:{$lastColumn}1");

                // 8. Wrap text aktif untuk kolom M (Tugas Rangkap)
                $sheet->getStyle($letter('tugas_rangkap_penugasan_sementara'))->getAlignment()->setWrapText(true);

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
                $genderVal = $createValidation('"'.implode(', ', array_values(Employee::GENDERS)).'"');
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
                    $sheet->getCell($letter('jenis_kelamin').$r)->setDataValidation(clone $genderVal);
                    $sheet->getCell($letter('agama').$r)->setDataValidation(clone $religionVal);
                    $sheet->getCell($letter('susunan_keluarga').$r)->setDataValidation(clone $familyVal);
                    $sheet->getCell($letter('status_karyawan').$r)->setDataValidation(clone $employmentVal);
                    $sheet->getCell($letter('unit_kerja').$r)->setDataValidation(clone $unitVal);
                    $sheet->getCell($letter('jabatan').$r)->setDataValidation($positionValidation($r));
                    $sheet->getCell($letter('status_penugasan').$r)->setDataValidation(clone $assignmentVal);
                    $sheet->getCell($letter('jalur_masuk').$r)->setDataValidation(clone $channelVal);
                    $sheet->getCell($letter('pendidikan_terakhir').$r)->setDataValidation(clone $educationVal);
                }
            },
        ];
    }
}
