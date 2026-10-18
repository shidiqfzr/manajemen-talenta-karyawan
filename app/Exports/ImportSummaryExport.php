<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ImportSummaryExport implements WithMultipleSheets
{
    public function __construct(protected array $reportData) {}

    public function sheets(): array
    {
        return [
            new class($this->reportData) implements FromArray, ShouldAutoSize, WithEvents, WithTitle
            {
                public function __construct(protected array $data) {}

                public function title(): string
                {
                    return 'Ringkasan & Metadata';
                }

                public function array(): array
                {
                    $summary = $this->data['summary'] ?? [];
                    $total = $summary['total_processed'] ?? 0;
                    $created = $summary['created'] ?? 0;
                    $updated = $summary['updated'] ?? 0;
                    $unchanged = $summary['unchanged'] ?? 0;
                    $skipped = $summary['skipped'] ?? 0;

                    return [
                        ['DOKUMEN BERITA ACARA & REKAPITULASI IMPOR DATA PEGAWAI'],
                        ['Sistem Informasi Manajemen Talenta & Formasi Pegawai - PTPN IV Regional V'],
                        ['PARAMETER SISTEM', 'NILAI / KETERANGAN'],
                        ['Waktu Eksekusi (WIB)', $this->data['timestamp'] ?? now()->toIso8601String()],
                        ['Administrator Pelaksana', $this->data['user_name'] ?? 'System Administrator'],
                        ['Email Administrator', $this->data['user_email'] ?? '-'],
                        ['Alamat IP Pelaksana', $this->data['ip_address'] ?? '127.0.0.1'],
                        ['Nama Berkas Excel Asal', $this->data['file_name'] ?? '-'],
                        ['Ukuran Berkas Asal', isset($this->data['file_size']) ? number_format($this->data['file_size'] / 1024, 1).' KB' : '-'],
                        ['Kebijakan Komit', 'Simpan Data Valid (Terisolasi)'],
                        [''],
                        ['RINGKASAN METRIK HASIL IMPOR', 'JUMLAH BARIS', 'PERSENTASE'],
                        ['Total Baris Diproses', $total, '100%'],
                        ['Data Pegawai Baru Ditambahkan', $created, $total > 0 ? round(($created / $total) * 100, 1).'%' : '0%'],
                        ['Data Pegawai Diperbarui (Update Atribut)', $updated, $total > 0 ? round(($updated / $total) * 100, 1).'%' : '0%'],
                        ['Data Pegawai Tetap / Identik (Unchanged)', $unchanged, $total > 0 ? round(($unchanged / $total) * 100, 1).'%' : '0%'],
                        ['Data Ditolak / Tidak Memenuhi Validasi', $skipped, $total > 0 ? round(($skipped / $total) * 100, 1).'%' : '0%'],
                        [''],
                        ['CATATAN INTEGRITAS & TATA KELOLA HR:'],
                        ['1. Data berhasil dikomit secara transaksional (DB::transaction) sesuai Pedoman Tata Kelola SDM & Peraturan Kepegawaian.'],
                        ['2. Seluruh aktivitas terekam permanen dalam log audit trail sesuai amanat UU PDP No. 27/2022.'],
                    ];
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();

                            // Title styling
                            $sheet->mergeCells('A1:C1');
                            $sheet->mergeCells('A2:C2');
                            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setARGB('FF15803D');
                            $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10)->getColor()->setARGB('FF475569');

                            // Table 1 header (Baris 3)
                            $sheet->getStyle('A3:B3')->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF15803D']],
                            ]);

                            // Table 2 header (Baris 12)
                            $sheet->getStyle('A12:C12')->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF15803D']],
                            ]);

                            // Borders
                            $sheet->getStyle('A3:B10')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
                            $sheet->getStyle('A12:C17')->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');

                            // Alignment for metric values
                            $sheet->getStyle('B12:C17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                            // Footer notes styling
                            $sheet->getStyle('A19')->getFont()->setBold(true)->setSize(10)->getColor()->setARGB('FF334155');
                            $sheet->getStyle('A20:A21')->getFont()->setSize(9)->getColor()->setARGB('FF64748B');
                        },
                    ];
                }
            },

            new class($this->reportData) implements FromArray, ShouldAutoSize, WithEvents, WithHeadings, WithTitle
            {
                public function __construct(protected array $data) {}

                public function title(): string
                {
                    return 'Daftar Data Diproses';
                }

                public function headings(): array
                {
                    return [
                        'NO',
                        'STATUS AKSI',
                        'NIK',
                        'NAMA LENGKAP',
                        'JABATAN',
                        'UNIT KERJA',
                        'STRATA (LEVEL)',
                        'GOLONGAN',
                        'RM LEVEL',
                        'STATUS KARYAWAN',
                    ];
                }

                public function array(): array
                {
                    $rows = $this->data['rows'] ?? [];
                    $export = [];
                    $no = 1;

                    foreach ($rows as $row) {
                        $action = $row['action'] ?? ($row['status'] ?? 'BARU');
                        $actionLabel = match ($action) {
                            'CREATE', 'BARU' => 'DATA BARU',
                            'UPDATE' => 'DIPERBARUI',
                            'UNCHANGED', 'TETAP' => 'TETAP (IDENTIK)',
                            default => $action,
                        };

                        $export[] = [
                            $no++,
                            $actionLabel,
                            ' '.(string) ($row['nik'] ?? ''), // leading space keeps string
                            (string) ($row['nama'] ?? ''),
                            (string) ($row['jabatan'] ?? ''),
                            (string) ($row['unit_kerja'] ?? ''),
                            (string) ($row['level'] ?? ''),
                            (string) ($row['golongan'] ?? '-'),
                            (string) ($row['rm_level'] ?? '-'),
                            (string) ($row['status_karyawan'] ?? '-'),
                        ];
                    }

                    return $export;
                }

                public function registerEvents(): array
                {
                    return [
                        AfterSheet::class => function (AfterSheet $event) {
                            $sheet = $event->sheet->getDelegate();
                            $lastRow = max(1, count($this->data['rows'] ?? []) + 1);

                            // Header style
                            $sheet->getStyle('A1:J1')->applyFromArray([
                                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF15803D']],
                                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                            ]);

                            if ($lastRow > 1) {
                                $sheet->getStyle("A1:J{$lastRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFCBD5E1');
                                $sheet->getStyle("A2:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("B2:B{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                                $sheet->getStyle("C2:C{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                            }
                        },
                    ];
                }
            },
        ];
    }
}
