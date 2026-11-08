<?php

namespace App\Exports;

use App\Models\JobHistory;
use App\Models\User;
use App\Services\MutasiService;
use Carbon\Carbon;
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

class MutasiHistoryExport implements FromQuery, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithProperties, WithTitle
{
    protected array $filters;

    protected ?User $user;

    protected ?Builder $query;

    protected int $rowNumber = 0;

    public function __construct(array $filters = [], ?User $user = null, ?Builder $query = null)
    {
        $this->query = $query;
        $this->filters = $filters;
        $this->user = $user;
    }

    /** Cakupan sumber catatan pada berkas, dicantumkan di metadata agar pemeriksa tahu isi berkas lengkap atau tidak. */
    private function scopeLabel(): string
    {
        $sumber = $this->filters['sumber'] ?? null;

        return match (true) {
            $sumber === 'resmi' => 'Mutasi Resmi saja',
            array_key_exists((string) $sumber, JobHistory::SUMBER_LABELS) => JobHistory::SUMBER_LABELS[$sumber].' saja',
            default => 'Semua Catatan',
        };
    }

    public function title(): string
    {
        return 'Log Audit Mutasi';
    }

    /**
     * Metadata dokumen resmi BUMN & Kepatuhan UU PDP No. 27/2022.
     */
    public function properties(): array
    {
        $userName = $this->user?->name ?? 'Administrator SDM';
        $userNik = $this->user?->nik ?? 'ADMIN';

        return [
            'creator' => "{$userName} ({$userNik})",
            'lastModifiedBy' => 'PTPN IV Regional V HRIS System',
            'title' => 'Log Audit Riwayat Mutasi & Formasi Jabatan',
            'description' => 'Dokumen Rekam Jejak Audit Mutasi Karyawan PTPN IV Regional V (Kepatuhan BPK / Inspektorat Holding)',
            'subject' => 'Audit Trail Mutasi Karyawan — Cakupan: '.$this->scopeLabel(),
            'keywords' => 'ptpn4, hris, mutasi, audit log, bpk, inspektorat, confidential',
            'category' => 'HR Audit Confidential Document',
            'manager' => 'Bagian SDM & TI PTPN IV Regional V',
            'company' => 'PT Perkebunan Nusantara IV (Persero) Regional V',
        ];
    }

    /**
     * Membangun streaming query mutasi terfilter memanfaatkan MutasiService (DRY).
     */
    public function query()
    {
        // A prebuilt query (for example the shared table's filtered rows) takes precedence over $filters.
        if ($this->query !== null) {
            return $this->query;
        }

        /** @var MutasiService $service */
        $service = app(MutasiService::class);

        return $service->getMutasiHistoryQuery(
            search: $this->filters['search'] ?? null,
            unitKerja: $this->filters['unitKerja'] ?? null,
            jenisMutasi: $this->filters['jenisMutasi'] ?? null,
            isApplied: $this->filters['isApplied'] ?? null,
            onlyDue: $this->filters['onlyDue'] ?? false,
            sortBy: $this->filters['sortBy'] ?? 'tmt_awal',
            sortDirection: $this->filters['sortDirection'] ?? 'desc',
            sumber: $this->filters['sumber'] ?? null,
        );
    }

    /**
     * Pemetaan data baris untuk 22 kolom standar kepatuhan audit enterprise.
     */
    public function map($record): array
    {
        $this->rowNumber++;

        // Status siklus
        $statusSiklus = 'Terjadwal (Staging)';
        if ($record->is_applied) {
            $statusSiklus = $record->tmt_akhir ? 'Selesai Menjabat' : 'Aktif Menjabat';
        }

        // Status regulasi
        // Catatan data kepegawaian (data awal, pembaruan data, impor) bukan SK mutasi: tidak dinilai sesuai/tidak
        // sesuai regulasi mutasi agar tidak menyesatkan pemeriksa.
        $statusRegulasi = $record->isRecordOnly()
            ? 'Bukan SK Mutasi (Catatan Data Kepegawaian)'
            : ($record->is_dispensasi ? 'Dispensasi Khusus Direksi' : 'Sesuai Standar Regulasi');

        return [
            $this->rowNumber,
            $record->id,
            self::sanitizeCellValue($record->employee_nik),
            self::sanitizeCellValue($record->employee?->nama ?? '—'),
            self::sanitizeCellValue($record->golongan ?? $record->employee?->golongan ?? '—'),
            self::sanitizeCellValue($record->level ?? $record->employee?->level ?? '—'),
            self::sanitizeCellValue($record->jabatan),
            self::sanitizeCellValue($record->unit_kerja),
            self::sanitizeCellValue($record->status_penugasan ?? 'Definitif'),
            self::sanitizeCellValue($record->jenis_mutasi_label),
            self::sanitizeCellValue($record->sumber_label),
            $record->tmt_awal ? Carbon::parse($record->tmt_awal)->format('d/m/Y') : '—',
            $record->tmt_akhir ? Carbon::parse($record->tmt_akhir)->format('d/m/Y') : 'Sekarang',
            $statusSiklus,
            self::sanitizeCellValue($record->nomor_sk ?? '—'),
            $record->tanggal_sk ? Carbon::parse($record->tanggal_sk)->format('d/m/Y') : '—',
            $statusRegulasi,
            self::sanitizeCellValue($record->is_dispensasi ? ($record->alasan_dispensasi ?: 'Dispensasi pertimbangan operasional pimpinan') : ($record->catatan ?: '—')),
            self::sanitizeCellValue($record->batch_id ? '#'.$record->batch_id : 'Tunggal'),
            $record->chain_order ?? 1,
            self::sanitizeCellValue($record->creator?->name ?? 'Administrator SDM'),
            $record->created_at ? $record->created_at->format('d/m/Y H:i:s') : '—',
        ];
    }

    /**
     * 22 Judul kolom enterprise audit compliance.
     */
    public function headings(): array
    {
        return [
            'NO',
            'ID LOG',
            'NIK KARYAWAN',
            'NAMA KARYAWAN',
            'GOLONGAN',
            'LEVEL',
            'JABATAN BARU',
            'UNIT KERJA BARU',
            'STATUS PENUGASAN',
            'JENIS MUTASI',
            'SUMBER CATATAN',
            'TMT EFEKTIF',
            'TMT AKHIR',
            'STATUS SIKLUS',
            'NOMOR SK',
            'TANGGAL SK',
            'STATUS REGULASI',
            'JUSTIFIKASI DISPENSASI / CATATAN',
            'ID BATCH GERBONG',
            'URUTAN GERBONG',
            'PENCATAT SISTEM (ADMIN)',
            'WAKTU PENCATATAN (WIB)',
        ];
    }

    /**
     * Format kolom teks agar NIK dan Nomor SK tidak kehilangan leading zero.
     */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT, // NIK Karyawan
            'O' => NumberFormat::FORMAT_TEXT, // Nomor SK
            'S' => NumberFormat::FORMAT_TEXT, // ID Batch
        ];
    }

    /**
     * Mitigasi CWE-1236 (Formula / CSV Injection).
     * Mencegah karakter formula (=, +, -, @, \t, \r) dieksekusi oleh aplikasi spreadsheet.
     */
    public static function sanitizeCellValue(mixed $val): string
    {
        $str = (string) ($val ?? '');
        if ($str === '') {
            return '';
        }

        if (in_array($str[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$str;
        }

        return $str;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                // 1. Freeze pane di D2 agar kolom NO, ID LOG, NIK, dan NAMA selalu sticky saat scroll horizontal
                $sheet->freezePane('E2');

                // 2. Tinggi baris header
                $sheet->getRowDimension(1)->setRowHeight(28);

                // 3. Styling Header: Hijau Korporat PTPN IV (Primary 800 - #1B5330)
                $sheet->getStyle('A1:V1')->applyFromArray([
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

                // 4. Auto-sizing untuk seluruh 22 kolom (A s.d. V)
                foreach (range('A', 'V') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                // 5. Aktifkan AutoFilter bawaan Excel pada baris header (A1:V1)
                $sheet->setAutoFilter('A1:V1');

                // 6. Pengaturan Header & Footer Cetak Keamanan (UU PDP No. 27/2022)
                $userName = $this->user?->name ?? 'Admin SDM';
                $sheet->getHeaderFooter()
                    ->setOddFooter('&L&8Dokumen Rahasia PT Perkebunan Nusantara IV Regional V - UU PDP No. 27/2022&R&8Diunduh oleh: '.$userName.' (&D &T) - Hal &P dari &N');
            },
        ];
    }
}
