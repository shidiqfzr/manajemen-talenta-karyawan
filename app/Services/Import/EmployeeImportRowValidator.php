<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;

class EmployeeImportRowValidator
{
    public const VALIDATION_RULES = [
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

    public const CUSTOM_MESSAGES = [
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

    /**
     * Cache in-memory lookup master unit & formasi aktif: [unit_name => ['exists' => bool, 'is_official' => bool, 'positions' => [level => [names]]]]
     */
    private array $unitPositionsCache = [];

    /**
     * Melakukan validasi menyeluruh (schema dasar + aturan bisnis kepegawaian).
     * Mengembalikan daftar string error. Jika array kosong, data dinyatakan valid.
     */
    public function validateRow(array &$data, array $existingEmployeesCache = []): array
    {
        $validator = Validator::make($data, self::VALIDATION_RULES, self::CUSTOM_MESSAGES);

        if ($validator->fails()) {
            return $validator->errors()->all();
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
        $existingEmployee = $existingEmployeesCache[$data['nik']] ?? null;
        if ($existingEmployee) {
            $existingJabatan = $existingEmployee['jabatan'] ?? ($existingEmployee instanceof Employee ? $existingEmployee->jabatan : '');
            $existingUnit = $existingEmployee['unit_kerja'] ?? ($existingEmployee instanceof Employee ? $existingEmployee->unit_kerja : '');
            $existingLevel = $existingEmployee['level'] ?? ($existingEmployee instanceof Employee ? $existingEmployee->level : '');

            $isSameJabatan = $this->isPositionEquivalent($existingJabatan, $data['jabatan']);

            if (! $isSameJabatan ||
                $existingUnit !== $data['unit_kerja'] ||
                $existingLevel !== $data['level']) {
                $businessErrors[] = 'Perubahan Jabatan/Unit Kerja/Level tidak diizinkan melalui import (Gunakan Modul Mutasi Karyawan).';
            } else {
                $data['jabatan'] = $existingJabatan;
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

        return $businessErrors;
    }

    /**
     * Memeriksa apakah dua nama jabatan setara (toleransi case-insensitive, tanda baca, dan spasi).
     */
    public function isPositionEquivalent(?string $positionA, ?string $positionB): bool
    {
        $posA = trim((string) $positionA);
        $posB = trim((string) $positionB);

        if (strcasecmp($posA, $posB) === 0) {
            return true;
        }

        return $this->matchPosition($posA, [$posB]) !== null
            || $this->matchPosition($posB, [$posA]) !== null;
    }

    /**
     * Mengambil data unit dan jabatan aktif dengan In-Memory Memoization (O(1) lookup).
     */
    public function getUnitPositionData(string $unitName, string $level): array
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
     * Pencocokan posisi toleran terhadap tanda baca dan spasi.
     */
    public function matchPosition(string $input, array $validPositions): ?string
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
            $str = preg_replace('/\btanamanan\b/iu', 'tanaman', $str);
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
}
