<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Unit;
use App\Services\Import\ConcurrentJobTextParser;
use App\Services\Import\EmployeeImportRowValidator;
use App\Services\KepegawaianPolicy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeesImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
{
    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $skipped = 0;

    public array $errors = [];

    public array $validRows = [];

    public array $failedRows = [];

    public array $sampleRows = [];

    public EmployeeImportRowValidator $validator;

    public ConcurrentJobTextParser $jobParser;

    public function __construct(
        public bool $dryRun = false,
        ?EmployeeImportRowValidator $validator = null,
        ?ConcurrentJobTextParser $jobParser = null,
        public ?string $actorName = null
    ) {
        $this->validator = $validator ?? new EmployeeImportRowValidator;
        $this->jobParser = $jobParser ?? new ConcurrentJobTextParser;
    }

    /**
     * Membatasi pembacaan impor hanya pada Sheet pertama ('Data Karyawan').
     * Sheet kedua ('Referensi Master Data') tidak diproses sebagai data pegawai.
     */
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    /**
     * Cache in-memory lookup pegawai eksisting: [nik => ['nik' => ..., 'jabatan' => ..., 'unit_kerja' => ..., 'level' => ...]]
     */
    private array $existingEmployeesCache = [];

    /**
     * Cache nama unit master (dimuat sekali per proses import).
     *
     * @var list<string>|null
     */
    private ?array $officialUnits = null;

    /**
     * Cache penampung NIK yang sudah diproses dalam batch berkas yang sama: [nik => rowNumber]
     */
    private array $seenNiksInFile = [];

    /**
     * Nomor baris terakhir yang diproses; berlanjut antar potongan (chunk) berkas.
     */
    private int $rowCursor = 1; // Header adalah baris 1

    /**
     * Berkas dibaca per potongan agar memori tidak membengkak pada berkas besar.
     */
    public function chunkSize(): int
    {
        return 500;
    }

    private const DATE_FIELDS = [
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'tanggal_mbt',
        'tanggal_pensiun',
    ];

    /**
     * Variasi penulisan unit pada berkas pegawai yang dipetakan ke unit master saat ini.
     * Hanya pemetaan yang tidak ambigu; nama unit yang sudah tidak ada pada formasi tidak dipetakan.
     */
    /**
     * Data keluarga hanya tampil di halaman detail pegawai (DESIGN.md §11, UU PDP): pratinjau impor
     * hanya menandai bahwa nilainya berubah tanpa menampilkan isi lama maupun baru.
     */
    private const HIDDEN_DIFF_FIELDS = ['susunan_keluarga'];

    private const HIDDEN_VALUE = 'Diperbarui (data pribadi, tidak ditampilkan)';

    public const UNIT_ALIASES = [
        'PROJECT MANAGEMENT OFFICE (PMO) INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG' => 'PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng',
        'PMO INVESTASI TANAMAN WILAYAH KALTIM & KALSEL-TENG' => 'PMO Investasi Tanaman Wilayah Kaltim & Kalsel-Teng',
        'PROJECT MANAGEMENT OFFICE (PMO) BATU BARA DAN BISNIS LAINNYA' => 'PMO Batu Bara & Bisnis Lainnya',
    ];

    public function collection(Collection $rows): void
    {
        // Pre-fetch seluruh NIK yang ada di batch rows untuk eliminasi N+1 query pegawai eksisting
        $rawNiks = [];
        foreach ($rows as $row) {
            foreach ($row as $k => $v) {
                if (str_contains(strtolower((string) $k), 'nik') && ! empty($v)) {
                    $rawNiks[] = $this->normalizeNik((string) $v);
                    break;
                }
            }
        }

        if (! empty($rawNiks)) {
            $this->existingEmployeesCache = Employee::whereIn('nik', array_unique($rawNiks))
                ->get()
                ->keyBy('nik')
                ->all();
        }

        $rowNumber = $this->rowCursor;

        foreach ($rows as $row) {
            $rowNumber++;

            // Abaikan jika seluruh kolom baris kosong
            if ($row->filter()->isEmpty()) {
                continue;
            }

            try {
                $rawRow = $row->toArray();
                $data = $this->prepareRowData($this->normalizeKeys($rawRow));

                // Deteksi NIK ganda di dalam berkas Excel yang sama
                if (! empty($data['nik'])) {
                    if (isset($this->seenNiksInFile[$data['nik']])) {
                        $prevRow = $this->seenNiksInFile[$data['nik']];
                        $this->recordValidationError(
                            $rowNumber,
                            ["NIK '{$data['nik']}' terdeteksi ganda (duplikat dari baris {$prevRow} pada berkas yang sama)."],
                            $data['nik'],
                            $rawRow,
                            $data['nama'] ?? null
                        );

                        continue;
                    }
                    $this->seenNiksInFile[$data['nik']] = $rowNumber;
                }

                if (! $this->validateRowData($data, $rowNumber, $rawRow)) {
                    continue;
                }

                if ($this->dryRun) {
                    $this->recordDryRunRow($data, $rawRow);
                } else {
                    $this->saveEmployee($data);
                }
            } catch (\Exception $e) {
                $this->handleProcessingError($rowNumber, $data['nik'] ?? 'Unknown', $e, $row->toArray());
            }
        }

        $this->rowCursor = $rowNumber;
    }

    private function recordDryRunRow(array $data, array $rawRow): void
    {
        $existingEmployee = $this->existingEmployeesCache[$data['nik']] ?? null;
        $diff = [];

        if ($existingEmployee) {
            if ($existingEmployee instanceof Employee && $this->isEmployeeIdentical($existingEmployee, $data)) {
                $action = 'UNCHANGED';
                $this->unchanged++;
            } else {
                $action = 'UPDATE';
                $this->updated++;
                if ($existingEmployee instanceof Employee) {
                    $diff = $this->getEmployeeDiff($existingEmployee, $data);
                }
            }
        } else {
            $action = 'CREATE';
            $this->created++;
        }

        $rowItem = $this->formatRowSummary($data, $action, $action, $diff);

        $this->validRows[] = $rowItem;
        $this->sampleRows[] = $rowItem;
    }

    /**
     * Membentuk struktur standar rekapitulasi baris data pegawai (DRY pattern).
     */
    private function formatRowSummary(array $data, string $action, ?string $statusLabel = null, array $diff = []): array
    {
        return [
            'action' => $action,
            'status' => $statusLabel ?? $action,
            'nik' => $data['nik'],
            'nama' => $data['nama'],
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit_kerja'],
            'level' => $data['level'],
            'golongan' => $data['golongan'] ?? '-',
            'rm_level' => $data['rm_level'] ?? '-',
            'status_karyawan' => $data['status_karyawan'] ?? '-',
            'status_penugasan' => $data['status_penugasan'] ?? 'Definitif',
            'tmt_bekerja' => $data['tmt_bekerja'] ?? '-',
            'tugas_rangkap' => $data['tugas_rangkap_penugasan_sementara'] ?? null,
            'diff' => $diff,
        ];
    }

    /**
     * Smart Auto-Healing untuk NIK: Excel sering menghilangkan leading-zero numerik.
     */
    public function normalizeNik(?string $nik): string
    {
        $rawNik = trim((string) $nik);
        if (ctype_digit($rawNik) && strlen($rawNik) >= 5 && strlen($rawNik) < 8) {
            return str_pad($rawNik, 8, '0', STR_PAD_LEFT);
        }

        return $rawNik;
    }

    public function prepareRowData(array $data): array
    {
        // 1. Smart Auto-Healing untuk NIK: Excel sering menghilangkan leading-zero numerik
        if (isset($data['nik'])) {
            $data['nik'] = $this->normalizeNik((string) $data['nik']);
        }

        // 2. Normalisasi format tanggal
        foreach (self::DATE_FIELDS as $field) {
            $data[$field] = $this->normalizeDate($data[$field] ?? null);
        }

        // 3. Auto-calculate Pensiun (56 thn) & MBT (55 thn) jika tanggal_lahir tersedia (DOMAIN.md Bab 5.1)
        if (! empty($data['tanggal_lahir'])) {
            if (empty($data['tanggal_pensiun'])) {
                $data['tanggal_pensiun'] = KepegawaianPolicy::tanggalPensiun($data['tanggal_lahir']);
            }
            if (empty($data['tanggal_mbt']) && ! empty($data['tanggal_pensiun'])) {
                $data['tanggal_mbt'] = KepegawaianPolicy::tanggalMbt($data['tanggal_pensiun']);
            }
        }

        // 4. Standarisasi format string
        if (isset($data['nama'])) {
            $data['nama'] = strtoupper(trim((string) $data['nama']));
        }
        if (isset($data['unit_kerja'])) {
            $data['unit_kerja'] = $this->normalizeUnitKerja((string) $data['unit_kerja']);
        }
        if (isset($data['jabatan'])) {
            $data['jabatan'] = trim((string) $data['jabatan']);
        }
        if (isset($data['golongan'])) {
            $data['golongan'] = strtoupper(trim((string) $data['golongan']));
        }
        if (isset($data['jenis_kelamin'])) {
            $jk = strtoupper(trim((string) $data['jenis_kelamin']));
            if (in_array($jk, ['L', 'LAKI-LAKI', 'PRIA', 'M', 'MALE'])) {
                $data['jenis_kelamin'] = 'L';
            } elseif (in_array($jk, ['P', 'PEREMPUAN', 'WANITA', 'F', 'FEMALE'])) {
                $data['jenis_kelamin'] = 'P';
            }
        }

        // Normalisasi status karyawan
        if (isset($data['status_karyawan'])) {
            $sk = trim((string) $data['status_karyawan']);
            if (strcasecmp($sk, 'PKWTT') === 0) {
                $data['status_karyawan'] = 'Tetap (PKWTT)';
            } elseif (strcasecmp($sk, 'PKWT') === 0) {
                $data['status_karyawan'] = 'Kontrak (PKWT)';
            }
        }

        $data['jalur_masuk'] = $data['jalur_masuk'] ?? 'Reguler';
        $data['status_penugasan'] = $data['status_penugasan'] ?? 'Definitif';

        return $data;
    }

    private function normalizeKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $cleanKey = str_replace('*', '', (string) $key);
            $snakeKey = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '_', trim($cleanKey)), '_'));
            $normalized[$snakeKey] = $value;
        }

        return $normalized;
    }

    private function validateRowData(array &$data, int $rowNumber, array $rawRow = []): bool
    {
        $errors = $this->validator->validateRow($data, $this->existingEmployeesCache);

        if (! empty($errors)) {
            $this->recordValidationError($rowNumber, $errors, $data['nik'] ?? null, $rawRow, $data['nama'] ?? null);

            return false;
        }

        return true;
    }

    private function saveEmployee(array $data): void
    {
        DB::transaction(function () use ($data) {
            $existingEmployee = $this->existingEmployeesCache[$data['nik']] ?? null;

            if ($existingEmployee instanceof Employee && $this->isEmployeeIdentical($existingEmployee, $data)) {
                $this->unchanged++;
                $this->validRows[] = $this->formatRowSummary($data, 'UNCHANGED', 'TETAP');
                Log::info('Employee unchanged via Excel import: NIK '.$this->maskNik($data['nik']));

                return;
            }

            $employee = Employee::firstOrNew(['nik' => $data['nik']]);
            if ($employee->exists && $this->actorName) {
                $employee->historyNote = "Perubahan data melalui impor Excel oleh {$this->actorName}";
            }
            $employee->fill($data)->save();

            // Jika terdapat data tugas rangkap / penugasan sementara (Kolom O), otomatis daftarkan ke job_histories
            if (! empty($data['tugas_rangkap_penugasan_sementara'])) {
                $this->jobParser->registerFromText($employee, (string) $data['tugas_rangkap_penugasan_sementara']);
            }

            // Sinkronkan cache in-memory untuk integritas batch
            $this->existingEmployeesCache[$data['nik']] = $employee;

            $action = $employee->wasRecentlyCreated ? 'CREATE' : 'UPDATE';
            $status = $employee->wasRecentlyCreated ? 'BARU' : 'UPDATE';
            $this->validRows[] = $this->formatRowSummary($data, $action, $status);

            if ($employee->wasRecentlyCreated) {
                $this->created++;
                Log::info('Created new employee via Excel import: NIK '.$this->maskNik($data['nik']));
            } else {
                $this->updated++;
                Log::info('Updated employee via Excel import: NIK '.$this->maskNik($data['nik']));
            }
        });
    }

    /**
     * Menghitung rincian atribut yang berubah (sebelum vs sesudah) untuk data status UPDATE.
     */
    public function getEmployeeDiff(Employee $existing, array $newData): array
    {
        $diff = [];
        $labels = [
            'nama' => 'Nama Lengkap',
            'level' => 'Strata (Level)',
            'golongan' => 'Golongan',
            'rm_level' => 'RM Level',
            'tempat_lahir' => 'Tempat Lahir',
            'tanggal_lahir' => 'Tanggal Lahir',
            'agama' => 'Agama',
            'susunan_keluarga' => 'Susunan Keluarga / PTKP',
            'status_karyawan' => 'Status Pegawai',
            'status_penugasan' => 'Status Penugasan',
            'pendidikan_terakhir' => 'Pendidikan Terakhir',
            'sekolah' => 'Sekolah / Universitas',
            'jenis_kelamin' => 'Jenis Kelamin',
            'jalur_masuk' => 'Jalur Masuk',
            'job_grade' => 'Job Grade',
            'person_grade' => 'Person Grade',
            'tmt_bekerja' => 'TMT Bekerja',
            'tanggal_diangkat_staf' => 'Tanggal Diangkat Staf',
            'tanggal_dalam_jabatan' => 'Tanggal Dalam Jabatan',
            'tmt_unit_kerja' => 'TMT Unit Kerja',
            'tanggal_mbt' => 'Tanggal MBT',
            'tanggal_pensiun' => 'Tanggal Pensiun',
            'tugas_rangkap_penugasan_sementara' => 'Tugas Rangkap',
        ];

        foreach ($labels as $field => $label) {
            if (! array_key_exists($field, $newData)) {
                continue;
            }

            $oldVal = trim((string) ($existing->{$field} ?? ''));
            $newVal = trim((string) ($newData[$field] ?? ''));

            if (in_array($field, self::DATE_FIELDS, true)) {
                $oldVal = $oldVal ? Carbon::parse($oldVal)->toDateString() : '';
                $newVal = $newVal ? Carbon::parse($newVal)->toDateString() : '';
            }

            if ($field === 'tugas_rangkap_penugasan_sementara') {
                if (empty($newVal)) {
                    continue;
                }
                $parsed = $this->jobParser->parse($newVal, $existing->unit_kerja);
                if ($parsed && $this->jobParser->isMatchingExisting($existing->nik, $parsed)) {
                    // Penugasan sementara sudah tercatat identik di database
                    continue;
                }
            }

            if (strcasecmp($oldVal, $newVal) !== 0) {
                $hidden = in_array($field, self::HIDDEN_DIFF_FIELDS, true);
                $diff[] = [
                    'field' => $field,
                    'label' => $label,
                    'old' => $hidden ? self::HIDDEN_VALUE : ($oldVal ?: '-'),
                    'new' => $hidden ? self::HIDDEN_VALUE : ($newVal ?: '-'),
                ];
            }
        }

        return $diff;
    }

    /**
     * Memeriksa apakah data pegawai dari Excel sama persis (identik) dengan data di database.
     */
    private function isEmployeeIdentical(Employee $existing, array $newData): bool
    {
        // 1. Perbandingan field teks & informasi dasar
        $textFields = [
            'nama',
            'level',
            'golongan',
            'tempat_lahir',
            'agama',
            'susunan_keluarga',
            'pendidikan_terakhir',
            'sekolah',
            'jenis_kelamin',
            'jalur_masuk',
            'status_penugasan',
            'status_karyawan',
        ];

        foreach ($textFields as $field) {
            if (array_key_exists($field, $newData)) {
                $existingVal = trim((string) ($existing->{$field} ?? ''));
                $newVal = trim((string) ($newData[$field] ?? ''));
                if (strcasecmp($existingVal, $newVal) !== 0) {
                    return false;
                }
            }
        }

        // 2. Unit Kerja & Jabatan
        if (array_key_exists('unit_kerja', $newData)) {
            $existingUnit = trim((string) $existing->unit_kerja);
            $newUnit = trim((string) $newData['unit_kerja']);
            if (strcasecmp($existingUnit, $newUnit) !== 0) {
                return false;
            }
        }

        if (array_key_exists('jabatan', $newData)) {
            $existingJabatan = (string) $existing->jabatan;
            $newJabatan = (string) $newData['jabatan'];
            if (! $this->validator->isPositionEquivalent($existingJabatan, $newJabatan)) {
                return false;
            }
        }

        // 3. RM Level
        if (array_key_exists('rm_level', $newData) && filled($newData['rm_level'])) {
            $existingRm = trim((string) ($existing->rm_level ?? ''));
            $newRm = trim((string) $newData['rm_level']);
            if (strcasecmp($existingRm, $newRm) !== 0) {
                return false;
            }
        }

        // 4. Grades (Job Grade & Person Grade)
        if (array_key_exists('job_grade', $newData) && filled($newData['job_grade'])) {
            if ((int) $existing->job_grade !== (int) $newData['job_grade']) {
                return false;
            }
        }
        if (array_key_exists('person_grade', $newData) && filled($newData['person_grade'])) {
            if ((int) $existing->person_grade !== (int) $newData['person_grade']) {
                return false;
            }
        }

        // 5. Tanggal (format Y-m-d)
        $dateFields = [
            'tanggal_dalam_jabatan',
            'tmt_unit_kerja',
            'tanggal_lahir',
            'tmt_bekerja',
            'tanggal_diangkat_staf',
        ];

        foreach ($dateFields as $df) {
            if (array_key_exists($df, $newData) && filled($newData[$df])) {
                $existingRaw = $existing->{$df};
                $existingDate = $existingRaw ? Carbon::parse($existingRaw)->toDateString() : null;
                $newDate = Carbon::parse($newData[$df])->toDateString();
                if ($existingDate !== $newDate) {
                    return false;
                }
            }
        }

        // 6. Tugas rangkap di Kolom O
        if (! empty($newData['tugas_rangkap_penugasan_sementara'])) {
            $parsed = $this->jobParser->parse((string) $newData['tugas_rangkap_penugasan_sementara'], $existing->unit_kerja);
            if (! $parsed || ! $this->jobParser->isMatchingExisting($existing->nik, $parsed)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Memeriksa apakah dua nama jabatan setara (delegasi ke validator).
     */
    public function isPositionEquivalent(?string $positionA, ?string $positionB): bool
    {
        return $this->validator->isPositionEquivalent($positionA, $positionB);
    }

    /**
     * Menormalisasi nama Unit Kerja dari data import Excel ke Unit Kerja resmi pada master formasi.
     */
    public function normalizeUnitKerja(?string $rawUnit): string
    {
        if (empty($rawUnit)) {
            return '';
        }

        $trimmed = trim($rawUnit);

        $this->officialUnits ??= Unit::officialNames();

        // 1. Direct match dengan unit resmi
        if (in_array($trimmed, $this->officialUnits, true)) {
            return $trimmed;
        }

        // 2. Case-insensitive match dengan unit resmi
        foreach ($this->officialUnits as $official) {
            if (strcasecmp($trimmed, $official) === 0) {
                return $official;
            }
        }

        // 2b. Abaikan perbedaan penulisan '&' dan 'dan' serta spasi ganda
        $canonical = fn (string $name): string => strtolower(preg_replace('/\s+/', ' ', str_replace('&', ' dan ', $name)));
        foreach ($this->officialUnits as $official) {
            if ($canonical($trimmed) === $canonical($official)) {
                return $official;
            }
        }

        // 3. Match dari daftar alias
        $upper = strtoupper($trimmed);
        if (isset(self::UNIT_ALIASES[$upper]) && in_array(self::UNIT_ALIASES[$upper], $this->officialUnits, true)) {
            return self::UNIT_ALIASES[$upper];
        }

        return $trimmed;
    }

    /**
     * Menyamarkan NIK (8 digit) pada teks log agar data pribadi tidak tercatat utuh (UU PDP No. 27/2022).
     */
    private function maskNik(string $text): string
    {
        return preg_replace_callback('/\b[0-9]{8}\b/', fn (array $m) => substr($m[0], 0, 2).'****'.substr($m[0], -2), $text);
    }

    private function recordValidationError(int $rowNumber, array $errors, ?string $nik = null, array $rawRow = [], ?string $nama = null): void
    {
        $this->recordFailedRow($rowNumber, $errors, $nik, $rawRow, $nama, 'warning');
    }

    private function handleProcessingError(int $rowNumber, string $nik, \Exception $e, array $rawRow = []): void
    {
        $this->recordFailedRow($rowNumber, ['Kendala data: '.$e->getMessage()], $nik, $rawRow, null, 'error');
    }

    /**
     * Pencatatan baris gagal divalidasi atau diproses (DRY pattern).
     */
    private function recordFailedRow(
        int $rowNumber,
        array $errors,
        ?string $nik = null,
        array $rawRow = [],
        ?string $nama = null,
        string $logLevel = 'warning'
    ): void {
        $this->skipped++;
        $namaPegawai = $nama ?? $rawRow['nama'] ?? $rawRow['nama_pegawai'] ?? $rawRow['nama_lengkap'] ?? null;

        $errorRecord = [
            'row' => $rowNumber,
            'nik' => $nik ?? 'Unknown',
            'nama' => $namaPegawai ?: '-',
            'jabatan' => $rawRow['jabatan'] ?? $rawRow['posisi'] ?? '-',
            'unit_kerja' => $rawRow['unit_kerja'] ?? $rawRow['unit'] ?? '-',
            'errors' => $errors,
        ];
        $this->errors[] = $errorRecord;

        $this->failedRows[] = [
            ...$errorRecord,
            'raw_data' => $rawRow,
        ];

        $logMsg = $this->maskNik($logLevel === 'error'
            ? "Failed to save NIK {$nik} on row {$rowNumber}: ".implode(', ', $errors)
            : "Row {$rowNumber} [NIK: ".($nik ?? 'N/A').'] skipped during import: '.implode(', ', $errors));

        if ($logLevel === 'error') {
            Log::error($logMsg);
        } else {
            Log::warning($logMsg);
        }
    }

    private function normalizeDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Tangani tanggal serial numerik dari Excel
        if (is_numeric($value)) {
            return $this->parseExcelDate($value);
        }

        // Coba format umum dd/mm/yyyy
        if ($date = $this->parseDateFormat($value, 'd/m/Y')) {
            return $date;
        }

        // Coba format yyyy-mm-dd
        if ($date = $this->parseDateFormat($value, 'Y-m-d')) {
            return $date;
        }

        // Fallback parsing generik Carbon
        return $this->parseGenericDate($value);
    }

    private function parseExcelDate(string $value): ?string
    {
        try {
            return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug("Failed to parse Excel date: {$value}");

            return null;
        }
    }

    private function parseDateFormat(string $value, string $format): ?string
    {
        try {
            return Carbon::createFromFormat($format, $value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug("Failed to parse date with format {$format}: {$value}");

            return null;
        }
    }

    private function parseGenericDate(string $value): ?string
    {
        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            Log::debug("Failed to parse date generically: {$value}");

            return null;
        }
    }

    public function getImportSummary(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'unchanged' => $this->unchanged,
            'skipped' => $this->skipped,
            'total_processed' => $this->created + $this->updated + $this->unchanged + $this->skipped,
            'valid_count' => $this->created + $this->updated + $this->unchanged,
            'has_errors' => ! empty($this->errors),
            'errors' => $this->errors,
            'sample_rows' => $this->sampleRows,
            'failed_count' => count($this->failedRows),
            'is_dry_run' => $this->dryRun,
        ];
    }
}
