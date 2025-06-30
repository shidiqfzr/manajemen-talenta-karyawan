<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

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
        'nik' => 'required|string',
        'nama' => 'required|string|max:255',
        'jabatan' => 'required|string|max:255',
        'level' => 'required|string|max:255',
        'unit_kerja' => 'required|string|max:255',
        'golongan_2024' => 'nullable|string|max:255',
        'tanggal_dalam_jabatan' => 'nullable|date',
        'tmt_unit_kerja' => 'nullable|date',
        'tempat_lahir' => 'nullable|string|max:255',
        'tanggal_lahir' => 'nullable|date',
        'tmt_bekerja' => 'nullable|date',
        'tanggal_diangkat_staf' => 'nullable|date',
        'susunan_keluarga' => 'nullable|string',
        'job_grader' => 'nullable|integer|max:255',
        'person_grade' => 'nullable|integer|max:255',
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

                if (!$this->validateRowData($data, $rowNumber)) {
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

        return $data;
    }

    private function normalizeKeys(array $row): array
    {
        $normalized = [];

        foreach ($row as $key => $value) {
            $snakeKey = strtolower(preg_replace('/\s+/', '_', trim($key)));
            $normalized[$snakeKey] = $value;
        }

        return $normalized;
    }

    private function validateRowData(array $data, int $rowNumber): bool
    {
        $validator = Validator::make($data, self::VALIDATION_RULES);

        if ($validator->fails()) {
            $this->recordValidationError($rowNumber, $validator->errors()->all());
            return false;
        }

        return true;
    }

    private function saveEmployee(array $data): void
    {
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
    }

    private function recordValidationError(int $rowNumber, array $errors): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $rowNumber,
            'errors' => $errors,
        ];

        Log::warning("Row {$rowNumber} skipped: " . implode(', ', $errors));
    }

    private function handleProcessingError(int $rowNumber, string $nik, \Exception $e): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $rowNumber,
            'nik' => $nik,
            'errors' => ['Database error: ' . $e->getMessage()],
        ];

        Log::error("Failed to save NIK {$nik}: " . $e->getMessage());
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
            'has_errors' => !empty($this->errors),
            'errors' => $this->errors,
        ];
    }
}
