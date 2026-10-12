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
    ];

    public function collection(Collection $rows): void
    {
        $rowNumber = 1; // Header adalah baris 1

        foreach ($rows as $row) {
            $rowNumber++;

            // Abaikan jika seluruh kolom baris kosong
            if ($row->filter()->isEmpty()) {
                continue;
            }

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
            $snakeKey = strtolower(preg_replace('/\s+/', '_', trim($cleanKey)));
            $normalized[$snakeKey] = $value;
        }

        return $normalized;
    }

    private function validateRowData(array $data, int $rowNumber): bool
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
            $this->recordValidationError($rowNumber, $validator->errors()->all(), $data['nik'] ?? null);

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
        $existingEmployee = Employee::where('nik', $data['nik'])->first();
        if ($existingEmployee) {
            if ($existingEmployee->jabatan !== $data['jabatan'] ||
                $existingEmployee->unit_kerja !== $data['unit_kerja'] ||
                $existingEmployee->level !== $data['level']) {
                $businessErrors[] = 'Perubahan Jabatan/Unit Kerja/Level tidak diizinkan melalui import (Gunakan Modul Mutasi Karyawan).';
            }
        } else {
            // Pegawai Baru: Validasi Master Unit Kerja & Formasi Jabatan (DOMAIN.md Bab 3 & Bab 3.5)
            $unit = Unit::where('nama', $data['unit_kerja'])->first();
            if (! $unit && ! in_array($data['unit_kerja'], Employee::OFFICIAL_UNITS, true)) {
                $businessErrors[] = "Unit Kerja '{$data['unit_kerja']}' tidak terdaftar di Master Unit Organisasi.";
            } elseif ($unit) {
                $validPositions = $unit->positions()
                    ->where('positions.is_active', true)
                    ->where('positions.level', $data['level'])
                    ->pluck('positions.nama')
                    ->toArray();

                if (count($validPositions) > 0 && ! in_array($data['jabatan'], $validPositions, true)) {
                    $businessErrors[] = "Jabatan '{$data['jabatan']}' tidak valid untuk Unit Kerja '{$data['unit_kerja']}' dan Level Strata '{$data['level']}'.";
                }
            }
        }

        if (! empty($businessErrors)) {
            $this->recordValidationError($rowNumber, $businessErrors, $data['nik']);

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

            if ($employee->wasRecentlyCreated) {
                $this->created++;
                Log::info("Created new employee via Excel import: NIK {$data['nik']}");
            } else {
                $this->updated++;
                Log::info("Updated employee via Excel import: NIK {$data['nik']}");
            }
        });
    }

    private function recordValidationError(int $rowNumber, array $errors, ?string $nik = null): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $rowNumber,
            'nik' => $nik ?? 'Unknown',
            'errors' => $errors,
        ];

        Log::warning("Row {$rowNumber} [NIK: ".($nik ?? 'N/A').'] skipped during import: '.implode(', ', $errors));
    }

    private function handleProcessingError(int $rowNumber, string $nik, \Exception $e): void
    {
        $this->skipped++;
        $this->errors[] = [
            'row' => $rowNumber,
            'nik' => $nik,
            'errors' => ['Database error: '.$e->getMessage()],
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
        ];
    }
}
