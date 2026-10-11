<?php

namespace App\Imports;

use App\Models\Employee;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

class EmployeesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;

    public int $updated = 0;

    public int $skipped = 0;

    public array $errors = [];

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
        'level' => 'required|string|max:255',
        'rm_level' => 'nullable|string|in:RM-1,RM-2,RM-3,RM-4',
        'unit_kerja' => 'required|string|max:255',
        'status_penugasan' => 'nullable|string|in:Definitif,Diperbantukan,Pejabat Sementara (Pjs),Tugas Belajar,Advisor',
        'status_karyawan' => 'required|string|max:50',
        'golongan' => 'nullable|string|max:255',
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
    ];

    public function collection(Collection $rows): void
    {
        $rowNumber = 1;

        foreach ($rows as $row) {
            $rowNumber++;

            try {
                $data = $this->prepareRowData($this->normalizeKeys($row->toArray()));

                if (! $this->validateRowData($data, $rowNumber)) {
                    continue;
                }

                $this->saveEmployee($data);
            } catch (\Exception $e) {
                $this->handleProcessingError($rowNumber, $data['nik'] ?? 'Unknown', $e);
            }
        }
    }

    private function prepareRowData(array $data): array
    {
        // Ensure NIK is string
        if (isset($data['nik'])) {
            $data['nik'] = (string) $data['nik'];
        }

        // Normalize date fields
        foreach (self::DATE_FIELDS as $field) {
            $data[$field] = $this->normalizeDate($data[$field] ?? null);
        }

        // Auto-calculate Pensiun (56 thn) & MBT (55 thn) if tanggal_lahir is present (DOMAIN.md Bab 5.1)
        if (! empty($data['tanggal_lahir'])) {
            if (empty($data['tanggal_pensiun'])) {
                $data['tanggal_pensiun'] = Employee::calculateTanggalPensiun($data['tanggal_lahir']);
            }
            if (empty($data['tanggal_mbt']) && ! empty($data['tanggal_pensiun'])) {
                $data['tanggal_mbt'] = Employee::calculateTanggalMbt($data['tanggal_pensiun']);
            }
        }

        // Standardize string fields
        if (isset($data['nama'])) {
            $data['nama'] = strtoupper(trim((string) $data['nama']));
        }
        if (isset($data['unit_kerja'])) {
            $data['unit_kerja'] = trim((string) $data['unit_kerja']);
        }
        if (isset($data['jenis_kelamin'])) {
            $jk = strtoupper(trim((string) $data['jenis_kelamin']));
            if (in_array($jk, ['L', 'LAKI-LAKI', 'PRIA', 'M', 'MALE'])) {
                $data['jenis_kelamin'] = 'L';
            } elseif (in_array($jk, ['P', 'PEREMPUAN', 'WANITA', 'F', 'FEMALE'])) {
                $data['jenis_kelamin'] = 'P';
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
            // Remove asterisk used for required fields indication
            $cleanKey = str_replace('*', '', (string) $key);
            $snakeKey = strtolower(preg_replace('/\s+/', '_', trim($cleanKey)));
            $normalized[$snakeKey] = $value;
        }

        return $normalized;
    }

    private function validateRowData(array $data, int $rowNumber): bool
    {
        $validator = Validator::make($data, self::VALIDATION_RULES, [
            'nik.regex' => 'Format NIK harus berupa 8 digit angka numerik.',
            'job_grade.between' => 'Job Grade harus berada dalam rentang 1 sampai 16.',
            'person_grade.between' => 'Person Grade harus berada dalam rentang 1 sampai 16.',
        ]);

        if ($validator->fails()) {
            $this->recordValidationError($rowNumber, $validator->errors()->all());

            return false;
        }

        // Business Logic Validations
        $existingEmployee = Employee::where('nik', $data['nik'])->first();

        if ($existingEmployee) {
            // Jika ada perubahan jabatan/unit/level, tolak karena harus via MutasiService
            if ($existingEmployee->jabatan !== $data['jabatan'] ||
                $existingEmployee->unit_kerja !== $data['unit_kerja'] ||
                $existingEmployee->level !== $data['level']) {
                $this->recordValidationError($rowNumber, ['Perubahan Jabatan/Unit Kerja/Level tidak diizinkan melalui import (Gunakan Mutasi Karyawan).']);

                return false;
            }
        } else {
            // Pegawai Baru: Validasi Formasi (Jabatan harus ada di Unit Kerja dengan Level yang sesuai)
            $unit = Unit::where('nama', $data['unit_kerja'])->first();
            if ($unit) {
                $validPositions = $unit->positions()
                    ->where('positions.is_active', true)
                    ->where('positions.level', $data['level'])
                    ->pluck('positions.nama')
                    ->toArray();

                if (count($validPositions) > 0 && ! in_array($data['jabatan'], $validPositions)) {
                    $this->recordValidationError($rowNumber, ['Jabatan Formasi tidak valid untuk Unit Kerja dan Level Strata Pegawai yang dipilih.']);

                    return false;
                }
            }
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

            if ($employee->wasRecentlyCreated) {
                $this->created++;
                Log::info("Created new employee: NIK {$data['nik']}");
            } else {
                $this->updated++;
                Log::info("Updated employee: NIK {$data['nik']}");
            }
        });
    }

    private function recordValidationError(int $rowNumber, array $errors): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $rowNumber,
            'errors' => $errors,
        ];

        Log::warning("Row {$rowNumber} skipped: ".implode(', ', $errors));
    }

    private function handleProcessingError(int $rowNumber, string $nik, \Exception $e): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $rowNumber,
            'nik' => $nik,
            'errors' => ['Database error: '.$e->getMessage()],
        ];

        Log::error("Failed to save NIK {$nik}: ".$e->getMessage());
    }

    private function normalizeDate(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        // Handle numeric Excel date
        if (is_numeric($value)) {
            return $this->parseExcelDate($value);
        }

        // Try dd/mm/yyyy format first
        if ($date = $this->parseDateFormat($value, 'd/m/Y')) {
            return $date;
        }

        // Fallback to generic parsing
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
        ];
    }
}
