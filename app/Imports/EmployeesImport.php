<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeesImport implements ToCollection, WithHeadingRow, WithMultipleSheets
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public array $errors = [];

    public array $validRows = [];

    public array $failedRows = [];

    public array $sampleRows = [];

    public function __construct(public bool $dryRun = false) {}

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
     * Cache in-memory lookup master unit & formasi aktif: [unit_name => ['exists' => bool, 'is_official' => bool, 'positions' => [level => [names]]]]
     */
    private array $unitPositionsCache = [];

    /**
     * Cache in-memory lookup pegawai eksisting: [nik => ['nik' => ..., 'jabatan' => ..., 'unit_kerja' => ..., 'level' => ...]]
     */
    private array $existingEmployeesCache = [];

    private ?array $allUnitNames = null;

    private ?array $allPositionNames = null;

    private const INDO_MONTHS = [
        'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4,
        'mei' => 5, 'juni' => 6, 'juli' => 7, 'agustus' => 8,
        'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12,
        'jan' => 1, 'feb' => 2, 'mar' => 3, 'apr' => 4,
        'jun' => 6, 'jul' => 7, 'agu' => 8, 'agt' => 8,
        'sep' => 9, 'okt' => 10, 'nov' => 11, 'des' => 12,
    ];

    private const DATE_FIELDS = [
        'tanggal_dalam_jabatan',
        'tmt_unit_kerja',
        'tanggal_lahir',
        'tmt_bekerja',
        'tanggal_diangkat_staf',
        'tanggal_mbt',
        'tanggal_pensiun',
    ];

    private const VALIDATION_RULES = [
        'nik' => ['required', 'string', 'regex:/^[0-9]{8}$/'],
        'nama' => 'required|string|max:255',
        'jabatan' => 'required|string|max:255',
        'level' => 'required|string|in:Karpim,Karpel',
        'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3,RM-4',
        'unit_kerja' => 'required|string|max:255',
        'status_penugasan' => 'nullable|string|in:Definitif,Diperbantukan,Pejabat Sementara (Pjs),Tugas Belajar,Advisor',
        'status_karyawan' => 'required|string|max:50',
        'golongan' => ['nullable', 'string', 'regex:/^(I|II|III|IV)[A-D](\/[0-9]{2})?$/i'],
        'tanggal_dalam_jabatan' => 'nullable|date',
        'tmt_unit_kerja' => 'nullable|date',
        'tempat_lahir' => 'nullable|string|max:255',
        'jenis_kelamin' => 'nullable|string|in:L,P,Laki-laki,Perempuan',
        'tanggal_lahir' => 'nullable|date',
        'tmt_bekerja' => 'nullable|date',
        'tanggal_diangkat_staf' => 'nullable|date',
        'susunan_keluarga' => 'nullable|string',
        'job_grade' => 'nullable|integer|between:1,16',
        'person_grade' => 'nullable|integer|between:1,16',
        'jalur_masuk' => 'nullable|string|in:CKP,Talent Scouting,RBB,Reguler',
        'tanggal_mbt' => 'nullable|date',
        'tanggal_pensiun' => 'nullable|date',
        'agama' => 'nullable|string|max:255',
        'pendidikan_terakhir' => 'nullable|string|max:255',
        'sekolah' => 'nullable|string|max:255',
        'tugas_rangkap_penugasan_sementara' => 'nullable|string',
    ];

    public function collection(Collection $rows): void
    {
        // Pre-fetch seluruh NIK yang ada di batch rows untuk eliminasi N+1 query pegawai eksisting
        $rawNiks = [];
        foreach ($rows as $row) {
            foreach ($row as $k => $v) {
                if (str_contains(strtolower((string) $k), 'nik') && ! empty($v)) {
                    $rawNik = trim((string) $v);
                    if (ctype_digit($rawNik) && strlen($rawNik) >= 5 && strlen($rawNik) < 8) {
                        $rawNik = str_pad($rawNik, 8, '0', STR_PAD_LEFT);
                    }
                    $rawNiks[] = $rawNik;
                    break;
                }
            }
        }

        if (! empty($rawNiks)) {
            $this->existingEmployeesCache = Employee::whereIn('nik', array_unique($rawNiks))
                ->select(['nik', 'jabatan', 'unit_kerja', 'level'])
                ->get()
                ->keyBy('nik')
                ->map(fn ($emp) => [
                    'nik' => $emp->nik,
                    'jabatan' => $emp->jabatan,
                    'unit_kerja' => $emp->unit_kerja,
                    'level' => $emp->level,
                ])
                ->toArray();
        }

        $rowNumber = 1; // Header adalah baris 1

        foreach ($rows as $row) {
            $rowNumber++;

            // Abaikan jika seluruh kolom baris kosong
            if ($row->filter()->isEmpty()) {
                continue;
            }

            try {
                $rawRow = $row->toArray();
                $data = $this->prepareRowData($this->normalizeKeys($rawRow));

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
    }

    private function recordDryRunRow(array $data, array $rawRow): void
    {
        $isExisting = isset($this->existingEmployeesCache[$data['nik']]);
        if ($isExisting) {
            $this->updated++;
        } else {
            $this->created++;
        }

        $rowItem = [
            'action' => $isExisting ? 'UPDATE' : 'CREATE',
            'nik' => $data['nik'],
            'nama' => $data['nama'],
            'jabatan' => $data['jabatan'],
            'unit_kerja' => $data['unit_kerja'],
            'level' => $data['level'],
            'golongan' => $data['golongan'] ?? '-',
            'status_karyawan' => $data['status_karyawan'] ?? '-',
            'status_penugasan' => $data['status_penugasan'] ?? 'Definitif',
            'tugas_rangkap' => $data['tugas_rangkap_penugasan_sementara'] ?? null,
        ];

        $this->validRows[] = $rowItem;

        if (count($this->sampleRows) < 15) {
            $this->sampleRows[] = $rowItem;
        }
    }

    public function prepareRowData(array $data): array
    {
        // 1. Smart Auto-Healing untuk NIK: Excel sering menghilangkan leading-zero numerik
        if (isset($data['nik'])) {
            $rawNik = trim((string) $data['nik']);
            if (ctype_digit($rawNik) && strlen($rawNik) >= 5 && strlen($rawNik) < 8) {
                $rawNik = str_pad($rawNik, 8, '0', STR_PAD_LEFT);
            }
            $data['nik'] = $rawNik;
        }

        // 2. Normalisasi format tanggal
        foreach (self::DATE_FIELDS as $field) {
            $data[$field] = $this->normalizeDate($data[$field] ?? null);
        }

        // 3. Auto-calculate Pensiun (56 thn) & MBT (55 thn) jika tanggal_lahir tersedia (DOMAIN.md Bab 5.1)
        if (! empty($data['tanggal_lahir'])) {
            if (empty($data['tanggal_pensiun'])) {
                $data['tanggal_pensiun'] = Employee::calculateTanggalPensiun($data['tanggal_lahir']);
            }
            if (empty($data['tanggal_mbt']) && ! empty($data['tanggal_pensiun'])) {
                $data['tanggal_mbt'] = Employee::calculateTanggalMbt($data['tanggal_pensiun']);
            }
        }

        // 4. Standarisasi format string
        if (isset($data['nama'])) {
            $data['nama'] = strtoupper(trim((string) $data['nama']));
        }
        if (isset($data['unit_kerja'])) {
            $data['unit_kerja'] = trim((string) $data['unit_kerja']);
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
        $customMessages = [
            'nik.required' => 'NIK wajib diisi.',
            'nik.regex' => 'Format NIK harus berupa 8 digit angka numerik (contoh: 13009988).',
            'nama.required' => 'Nama pegawai wajib diisi.',
            'jabatan.required' => 'Jabatan pegawai wajib diisi.',
            'level.required' => 'Level strata pegawai wajib diisi.',
            'level.in' => 'Level strata pegawai harus Karpim atau Karpel.',
            'unit_kerja.required' => 'Unit kerja wajib diisi.',
            'status_karyawan.required' => 'Status karyawan wajib diisi.',
            'golongan.regex' => 'Format Golongan tidak valid (Format baku: [Gol]/[Berkala], contoh: IIIA/00 atau IVA/02).',
            'job_grade.between' => 'Job Grade harus berada dalam rentang 1 sampai 16.',
            'person_grade.between' => 'Person Grade harus berada dalam rentang 1 sampai 16.',
        ];

        $validator = Validator::make($data, self::VALIDATION_RULES, $customMessages);

        if ($validator->fails()) {
            $this->recordValidationError($rowNumber, $validator->errors()->all(), $data['nik'] ?? null, $rawRow);

            return false;
        }

        $businessErrors = [];

        // 1. Validasi Strata vs Job/Person Grade (DOMAIN.md Bab 4.1 & 5.3)
        if ($data['level'] === 'Karpim') {
            if (isset($data['job_grade']) && ($data['job_grade'] < 11 || $data['job_grade'] > 16)) {
                $businessErrors[] = 'Untuk strata Karpim, Job Grade wajib berada dalam rentang 11 sampai 16.';
            }
            if (isset($data['person_grade']) && ($data['person_grade'] < 11 || $data['person_grade'] > 16)) {
                $businessErrors[] = 'Untuk strata Karpim, Person Grade wajib berada dalam rentang 11 sampai 16.';
            }
        } elseif ($data['level'] === 'Karpel') {
            if (isset($data['job_grade']) && ($data['job_grade'] < 1 || $data['job_grade'] > 10)) {
                $businessErrors[] = 'Untuk strata Karpel, Job Grade wajib berada dalam rentang 1 sampai 10.';
            }
            if (isset($data['person_grade']) && ($data['person_grade'] < 1 || $data['person_grade'] > 10)) {
                $businessErrors[] = 'Untuk strata Karpel, Person Grade wajib berada dalam rentang 1 sampai 10.';
            }
        }

        // 2. Validasi Kronologis Tanggal (DOMAIN.md Bab 5.3)
        if (! empty($data['tanggal_lahir']) && ! empty($data['tmt_bekerja'])) {
            $tglLahir = Carbon::parse($data['tanggal_lahir']);
            $tmtBekerja = Carbon::parse($data['tmt_bekerja']);
            if ($tglLahir->copy()->addYears(18)->gt($tmtBekerja)) {
                $businessErrors[] = 'TMT Bekerja minimal 18 tahun setelah Tanggal Lahir.';
            }
        }

        if (! empty($data['tmt_bekerja']) && ! empty($data['tanggal_diangkat_staf'])) {
            $tmtBekerja = Carbon::parse($data['tmt_bekerja']);
            $tglStaf = Carbon::parse($data['tanggal_diangkat_staf']);
            if ($tglStaf->lt($tmtBekerja)) {
                $businessErrors[] = 'Tanggal Diangkat Staf tidak boleh lebih awal dari TMT Bekerja.';
            }
        }

        if (! empty($data['tmt_bekerja']) && ! empty($data['tanggal_dalam_jabatan'])) {
            $tmtBekerja = Carbon::parse($data['tmt_bekerja']);
            $tglJabatan = Carbon::parse($data['tanggal_dalam_jabatan']);
            if ($tglJabatan->lt($tmtBekerja)) {
                $businessErrors[] = 'Tanggal Dalam Jabatan tidak boleh lebih awal dari TMT Bekerja.';
            }
        }

        // 3. Cek Perlindungan Mutasi untuk Pegawai Eksisting (DOMAIN.md Bab 5.2)
        $existingEmployee = $this->existingEmployeesCache[$data['nik']] ?? null;
        if ($existingEmployee) {
            $isSameJabatan = ($existingEmployee['jabatan'] === $data['jabatan']) ||
                $this->matchPosition($data['jabatan'], [$existingEmployee['jabatan']]) !== null;

            if (! $isSameJabatan ||
                $existingEmployee['unit_kerja'] !== $data['unit_kerja'] ||
                $existingEmployee['level'] !== $data['level']) {
                $businessErrors[] = 'Perubahan Jabatan/Unit Kerja/Level tidak diizinkan melalui import (Gunakan Modul Mutasi Karyawan).';
            } else {
                $data['jabatan'] = $existingEmployee['jabatan'];
            }
        } else {
            // Pegawai Baru: Validasi Master Unit Kerja & Formasi Jabatan via In-Memory Memoization (DOMAIN.md Bab 3 & Bab 3.5)
            [$unitExists, $isOfficial, $validPositions] = $this->getUnitPositionData($data['unit_kerja'], $data['level']);

            if (! $unitExists && ! $isOfficial) {
                $businessErrors[] = "Unit Kerja '{$data['unit_kerja']}' tidak terdaftar di Master Unit Organisasi.";
            } elseif ($unitExists && count($validPositions) > 0) {
                $matchedPosition = $this->matchPosition($data['jabatan'], $validPositions);
                if ($matchedPosition) {
                    $data['jabatan'] = $matchedPosition;
                } else {
                    $businessErrors[] = "Jabatan '{$data['jabatan']}' tidak valid untuk Unit Kerja '{$data['unit_kerja']}' dan Level Strata '{$data['level']}'.";
                }
            }
        }

        if (! empty($businessErrors)) {
            $this->recordValidationError($rowNumber, $businessErrors, $data['nik'], $rawRow);

            return false;
        }

        return true;
    }

    private function saveEmployee(array $data): void
    {
        DB::transaction(function () use ($data) {
            $employee = Employee::updateOrCreate(
                ['nik' => $data['nik']],
                $data
            );

            // Jika terdapat data tugas rangkap / penugasan sementara (Kolom O), otomatis daftarkan ke job_histories
            if (! empty($data['tugas_rangkap_penugasan_sementara'])) {
                $this->registerConcurrentJobFromText($employee, (string) $data['tugas_rangkap_penugasan_sementara']);
            }

            // Sinkronkan cache in-memory untuk integritas batch
            $this->existingEmployeesCache[$data['nik']] = [
                'nik' => $data['nik'],
                'jabatan' => $data['jabatan'],
                'unit_kerja' => $data['unit_kerja'],
                'level' => $data['level'],
            ];

            if ($employee->wasRecentlyCreated) {
                $this->created++;
                Log::info("Created new employee via Excel import: NIK {$data['nik']}");
            } else {
                $this->updated++;
                Log::info("Updated employee via Excel import: NIK {$data['nik']}");
            }
        });
    }

    /**
     * Mengambil data unit dan jabatan aktif dengan In-Memory Memoization (O(1) lookup).
     */
    private function getUnitPositionData(string $unitName, string $level): array
    {
        if (! isset($this->unitPositionsCache[$unitName])) {
            $unit = Unit::with(['positions' => function ($query) {
                $query->where('positions.is_active', true);
            }])->where('nama', $unitName)->first();

            if (! $unit) {
                $this->unitPositionsCache[$unitName] = [
                    'exists' => false,
                    'is_official' => in_array($unitName, Employee::OFFICIAL_UNITS, true),
                    'positions' => [],
                ];
            } else {
                $positionsByLevel = [];
                foreach ($unit->positions as $pos) {
                    $positionsByLevel[$pos->level][] = $pos->nama;
                }
                $this->unitPositionsCache[$unitName] = [
                    'exists' => true,
                    'is_official' => true,
                    'positions' => $positionsByLevel,
                ];
            }
        }

        $unitData = $this->unitPositionsCache[$unitName];
        $validPositions = $unitData['positions'][$level] ?? [];

        return [$unitData['exists'], $unitData['is_official'], $validPositions];
    }

    /**
     * Resolves matching position name tolerating punctuation and spacing variations
     * (e.g. '&' vs space, parentheses '()' vs none, case-insensitive).
     */
    private function matchPosition(string $input, array $validPositions): ?string
    {
        if (in_array($input, $validPositions, true)) {
            return $input;
        }

        foreach ($validPositions as $pos) {
            if (strcasecmp($input, $pos) === 0) {
                return $pos;
            }
        }

        $clean = function (string $str) {
            $str = str_ireplace(['&', 'dan'], ' ', $str);
            $str = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $str);
            $str = preg_replace('/\s+/', ' ', trim($str));

            return mb_strtolower($str);
        };

        $cleanedInput = $clean($input);
        foreach ($validPositions as $pos) {
            if ($clean($pos) === $cleanedInput) {
                return $pos;
            }
        }

        return null;
    }

    /**
     * Otomatis mendaftarkan penugasan sementara / tugas rangkap (Plt/Pjs) ke job_histories.
     */
    private function registerConcurrentJobFromText(Employee $employee, string $rawText): void
    {
        $parsed = $this->parseConcurrentJobText($rawText, $employee->unit_kerja);
        if (! $parsed) {
            return;
        }

        // Cek apakah riwayat penugasan aktif (tmt_akhir IS NULL) serupa sudah ada
        $existingJob = JobHistory::where('employee_nik', $employee->nik)
            ->whereNull('tmt_akhir')
            ->where('jabatan', $parsed['jabatan'])
            ->where('unit_kerja', $parsed['unit_kerja'])
            ->first();

        if ($existingJob) {
            $existingJob->update([
                'status_penugasan' => $parsed['status_penugasan'],
                'tmt_awal' => $parsed['tmt_awal'],
                'catatan' => 'Tugas Rangkap via Excel Import: '.$rawText,
            ]);
        } else {
            JobHistory::create([
                'employee_nik' => $employee->nik,
                'jabatan' => $parsed['jabatan'],
                'unit_kerja' => $parsed['unit_kerja'],
                'level' => $employee->level,
                'golongan' => $employee->golongan,
                'status_penugasan' => $parsed['status_penugasan'],
                'tmt_awal' => $parsed['tmt_awal'],
                'tmt_akhir' => null,
                'jenis_mutasi' => 'PENUGASAN',
                'catatan' => 'Tugas Rangkap via Excel Import: '.$rawText,
            ]);
        }
    }

    /**
     * Mem-parsing teks tugas rangkap di Kolom O untuk mengekstrak status penugasan (Plt/Pjs),
     * tanggal TMT, unit kerja penugasan, dan nama jabatan yang diemban.
     */
    private function parseConcurrentJobText(string $text, string $defaultUnit = ''): ?array
    {
        $text = trim($text);
        if (empty($text)) {
            return null;
        }

        // 1. Ekstrak Status Penugasan
        $status = 'Plt';
        if (preg_match('/pjs|pejabat\s+sementara/i', $text)) {
            $status = 'Pejabat Sementara (Pjs)';
        } elseif (preg_match('/diperbantukan/i', $text)) {
            $status = 'Diperbantukan';
        }

        // 2. Ekstrak Tanggal TMT
        $tmt = null;
        if (preg_match('/tmt\s*:?\s*(\d{1,2})\s+([a-zA-Z]+)\s+(\d{4})/i', $text, $m)) {
            $day = (int) $m[1];
            $monthStr = strtolower($m[2]);
            $year = (int) $m[3];
            $month = self::INDO_MONTHS[$monthStr] ?? null;
            if ($month) {
                $tmt = sprintf('%04d-%02d-%02d', $year, $month, $day);
            }
        } elseif (preg_match('/tmt\s*:?\s*(\d{4}-\d{2}-\d{2})/i', $text, $m)) {
            $tmt = $m[1];
        }

        // 3. Ekstrak Unit Kerja
        if ($this->allUnitNames === null) {
            $this->allUnitNames = Unit::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        }

        $matchedUnit = null;
        foreach ($this->allUnitNames as $unitName) {
            if (stripos($text, $unitName) !== false) {
                $matchedUnit = $unitName;
                break;
            }
        }

        if (! $matchedUnit) {
            $matchedUnit = $defaultUnit;
        }

        // 4. Ekstrak Jabatan
        $cleaned = $text;
        if ($tmt) {
            $cleaned = preg_replace('/tmt\s*:?.*$/i', '', $cleaned);
        }
        if ($matchedUnit && $matchedUnit !== $defaultUnit) {
            $cleaned = str_ireplace($matchedUnit, '', $cleaned);
        }
        $cleaned = preg_replace('/^(merangkap\s+(sebagai|sbg)?\s*)/i', '', $cleaned);
        $cleaned = preg_replace('/^(plt\.?|pjs\.?|pj\.?)\s*/i', '', trim($cleaned));
        $cleaned = trim(preg_replace('/\s+/', ' ', $cleaned));

        if ($this->allPositionNames === null) {
            $this->allPositionNames = Position::orderByRaw('LENGTH(nama) DESC')->pluck('nama')->toArray();
        }

        $matchedPos = null;
        foreach ($this->allPositionNames as $posName) {
            if (strcasecmp($cleaned, $posName) === 0) {
                $matchedPos = $posName;
                break;
            }
        }

        if (! $matchedPos) {
            $matchedPos = $this->matchPosition($cleaned, $this->allPositionNames);
        }

        if (! $matchedPos) {
            $matchedPos = filled($cleaned) ? $cleaned : $text;
        }

        return [
            'status_penugasan' => $status,
            'jabatan' => $matchedPos,
            'unit_kerja' => $matchedUnit,
            'tmt_awal' => $tmt ?? now()->toDateString(),
        ];
    }

    private function recordValidationError(int $rowNumber, array $errors, ?string $nik = null, array $rawRow = []): void
    {
        $this->skipped++;
        $errorRecord = [
            'row' => $rowNumber,
            'nik' => $nik ?? 'Unknown',
            'errors' => $errors,
        ];
        $this->errors[] = $errorRecord;

        $this->failedRows[] = [
            'row' => $rowNumber,
            'nik' => $nik ?? 'Unknown',
            'errors' => $errors,
            'raw_data' => $rawRow,
        ];

        Log::warning("Row {$rowNumber} [NIK: ".($nik ?? 'N/A').'] skipped during import: '.implode(', ', $errors));
    }

    private function handleProcessingError(int $rowNumber, string $nik, \Exception $e, array $rawRow = []): void
    {
        $this->skipped++;
        $errorRecord = [
            'row' => $rowNumber,
            'nik' => $nik,
            'errors' => ['Database error: '.$e->getMessage()],
        ];
        $this->errors[] = $errorRecord;

        $this->failedRows[] = [
            'row' => $rowNumber,
            'nik' => $nik,
            'errors' => ['Database error: '.$e->getMessage()],
            'raw_data' => $rawRow,
        ];

        Log::error("Failed to save NIK {$nik} on row {$rowNumber}: ".$e->getMessage());
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
            'skipped' => $this->skipped,
            'total_processed' => $this->created + $this->updated + $this->skipped,
            'has_errors' => ! empty($this->errors),
            'errors' => $this->errors,
            'sample_rows' => $this->sampleRows,
            'failed_count' => count($this->failedRows),
            'is_dry_run' => $this->dryRun,
        ];
    }
}
