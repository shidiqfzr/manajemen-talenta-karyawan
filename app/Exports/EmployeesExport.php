<?php

namespace App\Exports;

use App\Models\Employee;
use App\Models\User;
use App\Services\Import\EmployeeImportColumns;
use App\Services\ManPowerPlanningService;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithProperties;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class EmployeesExport implements FromQuery, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithProperties, WithTitle
{
    /** Columns the optional `sort` filter may order by (the directory's sortable columns). */
    private const SORTABLE = ['nama', 'jabatan', 'level', 'rm_level', 'golongan'];

    protected array $filters;

    protected ?User $user;

    public function __construct(array $filters = [], ?User $user = null)
    {
        $this->filters = $filters;
        $this->user = $user;
    }

    public function title(): string
    {
        return 'Data Karyawan';
    }

    /**
     * Properti dokumen metadata Excel sesuai regulasi UU PDP No. 27/2022
     * dan tata kelola keamanan informasi BUMN.
     */
    public function properties(): array
    {
        $userName = $this->user?->name ?? 'Administrator HRIS';
        $userNik = $this->user?->nik ?? 'ADMIN';

        return [
            'creator' => "{$userName} ({$userNik})",
            'lastModifiedBy' => 'PTPN IV Regional V HRIS System',
            'title' => 'Master Data Karyawan PTPN IV Regional V',
            'description' => 'Dokumen Rahasia Data Karyawan diekspor sesuai UU PDP No. 27/2022',
            'subject' => 'Data Karyawan & Formasi',
            'keywords' => 'ptpn4, hris, karyawan, formasi, confidential',
            'category' => 'HR Confidential Document',
            'manager' => 'Bagian SDM & TI PTPN IV Regional V',
            'company' => 'PT Perkebunan Nusantara IV (Persero) Regional V',
        ];
    }

    /**
     * Membangun query data karyawan dengan dukungan filter pencarian dinamis,
     * eager loading tugas rangkap (concurrentJobs), dan efisiensi memori (chunked query streaming).
     */
    public function query(): Builder
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

        if (! empty($this->filters['bidang'])) {
            $query->whereIn('nik', app(ManPowerPlanningService::class)->employeeNiksByBidang((string) $this->filters['bidang']));
        }

        if (! empty($this->filters['level'])) {
            $query->where('level', $this->filters['level']);
        }

        // Ekspor bawaan hanya karyawan aktif; status_kepegawaian (pensiun, MBT, menjelang MBT, lewat BUP) mengikuti filter daftar.
        $query->statusKepegawaian((string) ($this->filters['status_kepegawaian'] ?? ''));

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

        // Optional table sort (Daftar Karyawan export); whitelisted, defaults to nama ascending.
        $sort = in_array($this->filters['sort'] ?? null, self::SORTABLE, true) ? $this->filters['sort'] : 'nama';
        $direction = ($this->filters['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        $query->orderBy($sort, $direction);

        if ($sort !== 'nama') {
            $query->orderBy('nama', 'asc');
        }

        return $query;
    }

    /**
     * Header 26 Kolom Terstandarisasi (Memuat kolom tugas rangkap / penugasan sementara).
     */
    public function headings(): array
    {
        // Urutan kolom dari daftar kolom bersama; map() di bawah wajib menulis nilai dengan urutan yang sama.
        return EmployeeImportColumns::headings(EmployeeImportColumns::LAYOUT_TABULAR);
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
                $status = $cj->status_penugasan ?: Employee::STATUS_PLT;
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

        $clean = static function (mixed $val): string {
            $str = (string) ($val ?? '');
            if ($str !== '' && in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
                return "'".$str;
            }

            return $str;
        };

        return [
            $clean($employee->nik),
            $clean($employee->nama),
            $clean(Employee::GENDERS[$employee->jenis_kelamin] ?? $employee->jenis_kelamin),
            $clean($employee->tempat_lahir),
            $formatDate($employee->tanggal_lahir),
            $clean($employee->agama),
            $clean($employee->susunan_keluarga),

            $clean($employee->status_karyawan ?? 'Tetap (PKWTT)'),
            $clean($employee->jabatan),
            $clean($employee->level),
            $clean($employee->unit_kerja),
            $clean($employee->golongan),
            $clean($employee->rm_level),
            $clean($employee->status_penugasan ?? 'Definitif'),
            $clean($tugasRangkapText),

            $formatDate($employee->tmt_bekerja),
            $formatDate($employee->tanggal_diangkat_staf),
            $formatDate($employee->tanggal_dalam_jabatan),
            $formatDate($employee->tmt_unit_kerja),
            $clean($employee->jalur_masuk ?? 'Reguler'),
            $employee->job_grade !== null ? (int) $employee->job_grade : '',
            $employee->person_grade !== null ? (int) $employee->person_grade : '',

            $clean($employee->pendidikan_terakhir),
            $clean($employee->sekolah),
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
        $layout = EmployeeImportColumns::LAYOUT_TABULAR;
        $formats = [EmployeeImportColumns::letter('nik', $layout) => NumberFormat::FORMAT_TEXT];
        foreach (EmployeeImportColumns::fieldsOfType(EmployeeImportColumns::TYPE_DATE, $layout) as $field) {
            $formats[EmployeeImportColumns::letter($field, $layout)] = NumberFormat::FORMAT_DATE_YYYYMMDD;
        }

        return $formats;
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
                $sheet->getStyle('A1:'.EmployeeImportColumns::lastLetter(EmployeeImportColumns::LAYOUT_TABULAR).'1')->applyFromArray([
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
                foreach (EmployeeImportColumns::letters(EmployeeImportColumns::LAYOUT_TABULAR) as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. Aktifkan fitur AutoFilter bawaan Excel pada baris header (A1:Z1)
                $sheet->setAutoFilter('A1:'.EmployeeImportColumns::lastLetter(EmployeeImportColumns::LAYOUT_TABULAR).'1');

                // 6. Wrap text aktif untuk kolom O (Tugas Rangkap) agar multi-tugas tampil bertingkat rapi
                $sheet->getStyle(EmployeeImportColumns::letter('tugas_rangkap_penugasan_sementara', EmployeeImportColumns::LAYOUT_TABULAR))->getAlignment()->setWrapText(true);

                // 7. Pengaturan Halaman Cetak & Print Footer Keamanan (UU PDP No. 27/2022)
                $userName = $this->user?->name ?? 'Admin SDM';
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&8Dokumen Rahasia PT Perkebunan Nusantara IV Regional V - UU PDP No. 27/2022&R&8Diunduh oleh: '.$userName.' (&D &T) - Hal &P dari &N');
            },
        ];
    }
}
