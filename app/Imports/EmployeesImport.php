<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Services\Import\ConcurrentJobTextParser;
use App\Services\Import\EmployeeImportColumns;
use App\Services\Import\EmployeeImportDiffer;
use App\Services\Import\EmployeeImportRowValidator;
use App\Services\Import\ImportDateParser;
use App\Services\KepegawaianPolicy;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class EmployeesImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
{
    public int $created = 0;

    public int $updated = 0;

    public int $unchanged = 0;

    public int $skipped = 0;

    /**
     * Baris yang gagal karena kendala sistem (bukan aturan data). Komit nyata dibatalkan seluruhnya bila > 0.
     */
    public int $processingFailures = 0;

    /**
     * Peringatan tinjauan (tidak menghalangi impor): karyawan eksisting yang formasinya sudah tidak sesuai Master Formasi.
     *
     * @var list<array{row: int, nik: string, nama: string, messages: list<string>}>
     */
    public array $warnings = [];

    /**
     * Rekaman perubahan per karyawan pada komit nyata (untuk pembatalan impor): aksi, kondisi sebelum impor,
     * tugas rangkap yang disentuh, dan sidik jari kondisi sesudah impor.
     *
     * @var list<array{action: string, nik: string, before: ?array, jobs: list<array>, after_hash: string}>
     */
    public array $changes = [];

    public array $errors = [];

    public array $validRows = [];

    public array $failedRows = [];

    public array $sampleRows = [];

    public EmployeeImportRowValidator $validator;

    public ConcurrentJobTextParser $jobParser;

    private readonly EmployeeImportDiffer $differ;

    private readonly ImportDateParser $dates;

    public function __construct(
        public bool $dryRun = false,
        ?EmployeeImportRowValidator $validator = null,
        ?ConcurrentJobTextParser $jobParser = null,
        public ?string $actorName = null
    ) {
        $this->validator = $validator ?? new EmployeeImportRowValidator;
        $this->validator->lockSeats = ! $dryRun;
        $this->jobParser = $jobParser ?? new ConcurrentJobTextParser;
        $this->differ = new EmployeeImportDiffer($this->jobParser, $this->validator);
        $this->dates = new ImportDateParser;
    }

    /**
     * Membatasi pembacaan impor hanya pada Sheet pertama ('Data Karyawan').
     * Sheet kedua ('Referensi Master Data') tidak diproses sebagai data karyawan.
     */
    public function sheets(): array
    {
        return [
            0 => $this,
        ];
    }

    /**
     * Cache in-memory lookup karyawan eksisting: [nik => ['nik' => ..., 'jabatan' => ..., 'unit_kerja' => ..., 'level' => ...]]
     */
    private array $existingEmployeesCache = [];

    /**
     * Cache nama unit master (dimuat sekali per proses import).
     *
     * @var list<string>|null
     */
    private ?array $officialUnits = null;

    /**
     * Alias unit aktif dari Master Data (nama lama/variasi penulisan), dimuat sekali per proses import.
     *
     * @var array<string, string>|null
     */
    private ?array $unitAliases = null;

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

    public function collection(Collection $rows): void
    {
        // Pre-fetch seluruh NIK yang ada di batch rows untuk eliminasi N+1 query karyawan eksisting
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

                $warnings = [
                    ...(isset($this->existingEmployeesCache[$data['nik']]) ? $this->validator->seatDriftWarnings($data) : []),
                    ...$this->concurrentJobCheck($data)['warnings'],
                ];
                if ($warnings !== []) {
                    $this->warnings[] = ['row' => $rowNumber, 'nik' => $data['nik'], 'nama' => $data['nama'] ?? '-', 'messages' => $warnings];
                }

                if ($this->dryRun) {
                    $this->recordDryRunRow($data, $rawRow, $warnings);
                } else {
                    $this->saveEmployee($data, $warnings);
                }
            } catch (\Exception $e) {
                $this->handleProcessingError($rowNumber, $data['nik'] ?? 'Unknown', $e, $row->toArray());
            }
        }

        $this->rowCursor = $rowNumber;
    }

    private function recordDryRunRow(array $data, array $rawRow, array $warnings = []): void
    {
        $existingEmployee = $this->existingEmployeesCache[$data['nik']] ?? null;
        $diff = [];

        if ($existingEmployee) {
            if ($existingEmployee instanceof Employee && $this->differ->isIdentical($existingEmployee, $data)) {
                $action = 'UNCHANGED';
                $this->unchanged++;
            } else {
                $action = 'UPDATE';
                $this->updated++;
                if ($existingEmployee instanceof Employee) {
                    $diff = $this->differ->diff($existingEmployee, $data);
                }
            }
        } else {
            $action = 'CREATE';
            $this->created++;
        }

        // Label status memakai kosakata yang sama dengan jalur komit (BARU / UPDATE / TETAP); `action` tetap kunci baku.
        $rowItem = $this->formatRowSummary($data, $action, ['CREATE' => 'BARU', 'UNCHANGED' => 'TETAP'][$action] ?? $action, $diff, $warnings);

        $this->validRows[] = $rowItem;
        $this->sampleRows[] = $rowItem;
    }

    /**
     * Membentuk struktur standar rekapitulasi baris data karyawan (DRY pattern).
     */
    private function formatRowSummary(array $data, string $action, ?string $statusLabel = null, array $diff = [], array $warnings = []): array
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
            'warnings' => $warnings,
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
        foreach (ImportDateParser::FIELDS as $field) {
            $data[$field] = $this->dates->normalize($data[$field] ?? null);
        }

        // 3. Auto-calculate Pensiun (56 thn) & MBT (H-6 bulan) jika tanggal_lahir tersedia (DOMAIN.md Bab 5.1)
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
            // Judul kolom template terbaru 'Status Jabatan Utama' dan berkas lama 'Status Penugasan' sama-sama menjadi status_penugasan.
            $normalized[EmployeeImportColumns::fieldForHeadingKey(EmployeeImportColumns::normaliseHeading((string) $key))] = $value;
        }

        return $normalized;
    }

    private function validateRowData(array &$data, int $rowNumber, array $rawRow = []): bool
    {
        $errors = $this->validator->validateRow($data, $this->existingEmployeesCache);

        if (empty($errors) && ! empty($data['tugas_rangkap_penugasan_sementara'])) {
            $errors = $this->concurrentJobCheck($data)['errors'];
        }

        if (! empty($errors)) {
            $this->recordValidationError($rowNumber, $errors, $data['nik'] ?? null, $rawRow, $data['nama'] ?? null);

            return false;
        }

        return true;
    }

    /**
     * Hasil pemeriksaan teks Tugas Rangkap untuk baris ini (sekali per baris; baris sudah lolos validasi dasar).
     *
     * @return array{errors: list<string>, warnings: list<string>}
     */
    private function concurrentJobCheck(array $data): array
    {
        $text = (string) ($data['tugas_rangkap_penugasan_sementara'] ?? '');

        return $text === '' ? ['errors' => [], 'warnings' => []] : $this->jobParser->inspect($text, (string) ($data['unit_kerja'] ?? ''));
    }

    private function saveEmployee(array $data, array $warnings = []): void
    {
        DB::transaction(function () use ($data, $warnings) {
            $existingEmployee = $this->existingEmployeesCache[$data['nik']] ?? null;

            if ($existingEmployee instanceof Employee && $this->differ->isIdentical($existingEmployee, $data)) {
                $this->unchanged++;
                $this->validRows[] = $this->formatRowSummary($data, 'UNCHANGED', 'TETAP', [], $warnings);
                Log::info('Employee unchanged via Excel import: NIK '.$this->maskNik($data['nik']));

                return;
            }

            $employee = Employee::firstOrNew(['nik' => $data['nik']]);
            $before = $employee->exists
                ? Arr::except(Arr::only($employee->getRawOriginal(), $employee->getFillable()), ['nik'])
                : null;
            if ($employee->exists && $this->actorName) {
                $employee->historyNote = "Perubahan data melalui impor Excel oleh {$this->actorName}";
            }
            $employee->fill($data)->save();

            // Jika terdapat data tugas rangkap / penugasan sementara (Kolom O), otomatis daftarkan ke job_histories
            $jobs = [];
            if (! empty($data['tugas_rangkap_penugasan_sementara'])) {
                $jobs = $this->jobParser->registerAllFromText($employee, (string) $data['tugas_rangkap_penugasan_sementara']);
            }

            $this->changes[] = [
                'action' => $employee->wasRecentlyCreated ? 'CREATE' : 'UPDATE',
                'nik' => (string) $employee->nik,
                'before' => $before,
                'jobs' => $jobs,
                'after_hash' => Employee::stateHash(Employee::findOrFail($employee->nik)),
            ];

            // Sinkronkan cache in-memory untuk integritas batch
            $this->existingEmployeesCache[$data['nik']] = $employee;

            $action = $employee->wasRecentlyCreated ? 'CREATE' : 'UPDATE';
            $status = $employee->wasRecentlyCreated ? 'BARU' : 'UPDATE';
            $this->validRows[] = $this->formatRowSummary($data, $action, $status, [], $warnings);

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
        $key = UnitAlias::keyFor($trimmed);
        foreach ($this->officialUnits as $official) {
            if ($key === UnitAlias::keyFor($official)) {
                return $official;
            }
        }

        // 3. Nama lama/variasi penulisan yang tercatat di Master Data (alias unit aktif)
        $this->unitAliases ??= UnitAlias::activeMap();
        if (isset($this->unitAliases[$key])) {
            return $this->unitAliases[$key];
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
        $this->processingFailures++;
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
            'warning_count' => count($this->warnings),
            'is_dry_run' => $this->dryRun,
        ];
    }
}
