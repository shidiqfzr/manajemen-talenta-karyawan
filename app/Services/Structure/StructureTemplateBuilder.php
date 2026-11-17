<?php

namespace App\Services\Structure;

use App\Services\Formasi\FormasiExcelParser;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Template lampiran SK struktur organisasi: berkas Excel berisi struktur saat ini dalam format yang dibaca
 * FormasiExcelParser, ditambah lembar petunjuk. Admin menyesuaikan barisnya dengan SK dari Holding lalu mengunggahnya;
 * template yang tidak diubah menghasilkan selisih kosong. Tidak memuat data pribadi karyawan.
 */
class StructureTemplateBuilder
{
    public const FILE_NAME = 'template-struktur-organisasi.xlsx';

    public function __construct(private readonly StructureReader $reader) {}

    public function build(): Spreadsheet
    {
        $book = new Spreadsheet;
        $this->fillStructureSheet($book);
        $this->fillInstructionSheet($book);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    /** Menyusun dan menulis template ke $target (jalur berkas atau `php://output`). */
    public function writeTo(string $target): void
    {
        (new Xlsx($this->build()))->save($target);
    }

    private function fillStructureSheet(Spreadsheet $book): void
    {
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Struktur Organisasi');

        foreach (FormasiExcelParser::HEADERS_STRUKTUR as $index => $header) {
            $sheet->setCellValueExplicit([$index + 1, 1], $header, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('2F8250');
        $sheet->freezePane('A2');

        $row = 2;
        foreach ($this->reader->current()['units'] as $unit => $data) {
            if (($data['is_active'] ?? true) === false) {
                continue;
            }

            foreach ($data['seats'] as $jabatan => $seat) {
                // Teks dituliskan sebagai string agar nama yang diawali "=" tidak pernah dibaca sebagai rumus.
                $sheet->setCellValue([1, $row], $row - 1);
                $sheet->setCellValueExplicit([2, $row], $unit, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit([3, $row], '-', DataType::TYPE_STRING);
                $sheet->setCellValueExplicit([4, $row], $jabatan, DataType::TYPE_STRING);
                $sheet->setCellValueExplicit([5, $row], str_replace('-', ' ', $seat['rm_level']), DataType::TYPE_STRING);
                $sheet->setCellValueExplicit([6, $row], (string) $seat['bidang'], DataType::TYPE_STRING);
                $sheet->setCellValue([7, $row], (int) $seat['kuota']);
                $row++;
            }
        }

        foreach (['A' => 6, 'B' => 44, 'C' => 20, 'D' => 52, 'E' => 12, 'F' => 10, 'G' => 10, 'H' => 36] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    private function fillInstructionSheet(Spreadsheet $book): void
    {
        $sheet = $book->createSheet();
        $sheet->setTitle('Petunjuk');

        $lines = [
            'Cara menyiapkan Lampiran SK',
            '',
            '1. Lembar "Struktur Organisasi" berisi struktur organisasi saat ini dalam format yang dibaca aplikasi. Jangan mengubah judul kolom dan jangan menambah lembar di depannya.',
            '2. Sesuaikan baris dengan Lampiran SK yang diterima dari Holding:',
            '   - Ubah Kuota, Bidang, atau Strata RM pada baris jabatan yang berubah.',
            '   - Tambah baris baru untuk Formasi Jabatan baru (isi Unit/Bagian, Jabatan, Strata RM, Bidang, dan Kuota).',
            '   - Hapus baris untuk Formasi Jabatan yang dihilangkan oleh SK.',
            '   - Unit baru: tulis nama unit pada kolom Unit/Bagian; kode, wilayah, dan urutan unit diisi pada tahap tinjau.',
            '   - Unit atau jabatan yang hanya berganti nama: tulis nama barunya, lalu pasangkan dengan nama lamanya pada tahap tinjau agar riwayat tetap utuh.',
            '3. Unit yang tidak diatur SK boleh dihapus seluruh barisnya dari berkas; unit yang tidak tercantum tidak diubah. Untuk unit yang tercantum, seluruh jabatannya harus ada: jabatan yang tidak tercantum dianggap dihapus oleh SK.',
            '4. Format isian: Strata RM ditulis RM 1, RM 2, RM 3, atau RM 5; Bidang KEU, TAN, TEK, atau UMU; Kuota bilangan bulat (kosong berarti 0, untuk formasi berbasis norma). Kolom No, Sub Bagian, dan Keterangan tidak disimpan.',
            '5. Simpan sebagai berkas .xlsx, lalu unggah pada formulir "Catat SK dari Holding". Aplikasi menampilkan selisih terhadap struktur saat ini untuk Anda tinjau sebelum SK diajukan.',
            '',
            'Template ini tidak memuat data pribadi karyawan.',
        ];

        foreach ($lines as $index => $line) {
            $sheet->setCellValueExplicit([1, $index + 1], $line, DataType::TYPE_STRING);
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getColumnDimension('A')->setWidth(140);
        $sheet->getStyle('A1:A'.count($lines))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }
}
