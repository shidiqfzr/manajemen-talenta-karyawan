<?php

namespace App\Exports;

use App\Models\Employee;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EmployeesExport implements FromQuery, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithTitle
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function title(): string
    {
        return 'Data Karyawan';
    }

    /**
     * Membangun query data pegawai dengan dukungan filter pencarian dinamis,
     * eager loading tugas rangkap (concurrentJobs), dan efisiensi memori (chunked query streaming).
     */
    public function query()
    {
        $query = Employee::with(['concurrentJobs']);

        if (! empty($this->filters['niks'])) {
            $niks = is_array($this->filters['niks']) ? $this->filters['niks'] : explode(',', (string) $this->filters['niks']);
            $query->whereIn('nik', $niks);
        }

        if (! empty($this->filters['search'])) {
            $query->search($this->filters['search']);
        }

        if (! empty($this->filters['jabatan'])) {
            $query->where('jabatan', $this->filters['jabatan']);
        }

        if (! empty($this->filters['level'])) {
            $query->where('level', $this->filters['level']);
        }

        if (! empty($this->filters['rm_level'])) {
            $query->where('rm_level', $this->filters['rm_level']);
        }

        if (! empty($this->filters['unit_kerja'])) {
            $query->where('unit_kerja', $this->filters['unit_kerja']);
        }

        if (! empty($this->filters['golongan'])) {
            $query->where('golongan', $this->filters['golongan']);
        }

        if (! empty($this->filters['status_penugasan'])) {
            $status = $this->filters['status_penugasan'];
            if ($status === 'sementara') {
                $query->whereHas('concurrentJobs');
            } elseif ($status === 'definitif') {
                $query->whereDoesntHave('concurrentJobs');
            } else {
                $query->whereHas('concurrentJobs', function ($q) use ($status) {
                    $q->where('status_penugasan', $status);
                });
            }
        }

        if (! empty($this->filters['status_khusus'])) {
            $khusus = $this->filters['status_khusus'];
            if ($khusus === 'sanksi') {
                $query->sanctionAll();
            } elseif ($khusus === 'overflow') {
                $query->overflow();
            } elseif ($khusus === 'rangkap') {
                $query->whereHas('concurrentJobs');
            }
        }

        if (! empty($this->filters['tahun_masuk'])) {
            $query->whereYear('tmt_bekerja', $this->filters['tahun_masuk']);
        }

        if (! empty($this->filters['bulan_masuk'])) {
            $query->whereMonth('tmt_bekerja', $this->filters['bulan_masuk']);
        }

        return $query->orderBy('nama', 'asc');
    }

    /**
     * Header 26 Kolom Terstandarisasi (Memuat kolom tugas rangkap / penugasan sementara).
     */
    public function headings(): array
    {
        return [
            // Identitas Utama (Kolom A - G)
            'NIK',
            'NAMA',
            'JENIS KELAMIN',
            'TEMPAT LAHIR',
            'TANGGAL LAHIR',
            'AGAMA',
            'SUSUNAN KELUARGA',

            // Info Jabatan & Formasi (Kolom H - O)
            'STATUS KARYAWAN',
            'JABATAN',
            'LEVEL',
            'UNIT KERJA',
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

    /**
     * Transformasi setiap record Employee ke baris Excel dengan format presisi.
     */
    public function map($employee): array
    {
        $formatDate = static function ($date): string {
            if (empty($date)) {
                return '';
            }

            return substr((string) $date, 0, 10);
        };

        // Format rincian tugas rangkap aktif (Plt / Pjs / Diperbantukan)
        $tugasRangkapList = [];
        if ($employee->relationLoaded('concurrentJobs') && $employee->concurrentJobs->isNotEmpty()) {
            foreach ($employee->concurrentJobs as $cj) {
                $status = $cj->status_penugasan ?: 'Plt';
                $details = [];
                if (! empty($cj->nomor_sk)) {
                    $details[] = 'SK: '.$cj->nomor_sk;
                }
                if (! empty($cj->tmt_awal)) {
                    $details[] = 'TMT: '.$formatDate($cj->tmt_awal);
                }
                $extra = ! empty($details) ? ' ('.implode(', ', $details).')' : '';
                $tugasRangkapList[] = "[{$status}] {$cj->jabatan} — {$cj->unit_kerja}{$extra}";
            }
        }
        $tugasRangkapText = ! empty($tugasRangkapList) ? implode("\n", $tugasRangkapList) : '';

        return [
            (string) $employee->nik,
            (string) $employee->nama,
            $employee->jenis_kelamin ?? '',
            $employee->tempat_lahir ?? '',
            $formatDate($employee->tanggal_lahir),
            $employee->agama ?? '',
            $employee->susunan_keluarga ?? '',

            $employee->status_karyawan ?? 'Tetap (PKWTT)',
            $employee->jabatan ?? '',
            $employee->level ?? '',
            $employee->unit_kerja ?? '',
            $employee->golongan ?? '',
            $employee->rm_level ?? '',
            $employee->status_penugasan ?? 'Definitif',
            $tugasRangkapText,

            $formatDate($employee->tmt_bekerja),
            $formatDate($employee->tanggal_diangkat_staf),
            $formatDate($employee->tanggal_dalam_jabatan),
            $formatDate($employee->tmt_unit_kerja),
            $employee->jalur_masuk ?? 'Reguler',
            $employee->job_grade !== null ? (int) $employee->job_grade : '',
            $employee->person_grade !== null ? (int) $employee->person_grade : '',

            $employee->pendidikan_terakhir ?? '',
            $employee->sekolah ?? '',
            $formatDate($employee->tanggal_mbt),
            $formatDate($employee->tanggal_pensiun),
        ];
    }

    /**
     * Format sel eksplisit untuk mencegah kehilangan leading-zero pada NIK
     * dan memastikan konsistensi format tanggal ISO.
     */
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

    /**
     * Tata letak dan styling spreadsheet enterprise PTPN IV Regional V.
     */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Freeze baris pertama agar header selalu terlihat saat scrolling data banyak
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

                // 4. Auto-sizing untuk seluruh kolom A s.d. Z
                foreach (range('A', 'Z') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. Aktifkan fitur AutoFilter bawaan Excel pada baris header (A1:Z1)
                $sheet->setAutoFilter('A1:Z1');

                // 6. Wrap text aktif untuk kolom O (Tugas Rangkap) agar multi-tugas tampil bertingkat rapi
                $sheet->getStyle('O')->getAlignment()->setWrapText(true);
            },
        ];
    }
}
