<?php

namespace App\Imports;

use App\Models\Employee;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Carbon\Carbon;

class EmployeesImport implements ToCollection, WithHeadingRow
{
    public $created = 0;
    public $updated = 0;
    public $skipped = 0;
    public $errors = [];

    public function collection(Collection $rows)
    {
        $rowNumber = 1; // Starting from 2 if heading row exists, but you can adjust as needed

        foreach ($rows as $row) {
            $rowNumber++;

            // Convert row to array
            $data = $row->toArray();

            // Normalize date fields to Y-m-d or null
            $dateFields = [
                'tanggal_dalam_jabatan',
                'tmt_unit_kerja',
                'tanggal_lahir',
                'tmt_bekerja',
                'tanggal_diangkat_staf',
                'tanggal_mbt',
                'tanggal_pensiun',
            ];

            foreach ($dateFields as $field) {
                if (isset($data[$field]) && $data[$field] !== null && $data[$field] !== '') {
                    $parsedDate = $this->parseDate($data[$field]);
                    $data[$field] = $parsedDate ?: null;  // set null if parsing fails
                } else {
                    $data[$field] = null;
                }
            }

            // Validate data
            $validator = Validator::make($data, [
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
                'job_grader' => 'nullable|string|max:255',
                'person_grade' => 'nullable|string|max:255',
                'tanggal_mbt' => 'nullable|date',
                'tanggal_pensiun' => 'nullable|date',
                'agama' => 'nullable|string|max:255',
                'pendidikan_terakhir' => 'nullable|string|max:255',
                'sekolah' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                $this->skipped++;
                $this->errors[] = [
                    'row' => $rowNumber,
                    'errors' => $validator->errors()->all(),
                ];
                continue;
            }

            // Insert or update employee record
            $employee = Employee::updateOrCreate(
                ['nik' => $data['nik']],
                $data
            );

            $employee->wasRecentlyCreated ? $this->created++ : $this->updated++;
        }
    }

    private function parseDate($value)
    {
        if (is_numeric($value)) {
            try {
                return ExcelDate::excelToDateTimeObject($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        // Try to parse with 'd-m-Y' format
        try {
            return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
        } catch (\Exception $e) {
            // Fallback: try generic date parse (if your input may vary)
            try {
                return Carbon::parse($value)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }
    }
}
