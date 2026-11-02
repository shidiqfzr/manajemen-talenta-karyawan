<?php

namespace App\Services\Import;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use App\Services\KepegawaianPolicy;
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
        'job_grade' => 'nullable|integer|between:'.KepegawaianPolicy::GRADE_MIN.','.KepegawaianPolicy::GRADE_MAX,
        'person_grade' => 'nullable|integer|between:'.KepegawaianPolicy::GRADE_MIN.','.KepegawaianPolicy::GRADE_MAX,
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
     * Cache in-memory lookup master unit & formasi jabatan aktif:
     * [unit_name => ['exists' => bool, 'positions' => [level => [nama => ['rm_level' => ?string, 'kuota' => int, 'pool' => bool]]]]]
     */
    private array $unitPositionsCache = [];

    /**
     * Jumlah pemangku definitif per unit & jabatan: [unit_name => [jabatan => int]].
     * Dimuat sekali per unit dari database, lalu ditambah setiap baris baru yang lolos dalam batch yang sama.
     */
    private array $occupancyCache = [];

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
        if ($range = KepegawaianPolicy::gradeRange($data['level'])) {
            [$min, $max] = $range;
            foreach (['job_grade' => 'Job Grade', 'person_grade' => 'Person Grade'] as $field => $label) {
                if (isset($data[$field]) && ($data[$field] < $min || $data[$field] > $max)) {
                    $businessErrors[] = "Untuk strata {$data['level']}, {$label} wajib berada dalam rentang {$min} sampai {$max}.";
                }
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

                // RM Band melekat pada jabatan (Master Formasi); import hanya boleh mengonfirmasi, bukan menentukan.
                $masterRm = Position::getAllCached()->get($existingJabatan)?->rm_level;
                if ($masterRm !== null && filled($data['rm_level'] ?? null) && $data['rm_level'] !== $masterRm) {
                    $businessErrors[] = "RM Level '{$data['rm_level']}' tidak sesuai dengan formasi jabatan '{$existingJabatan}' (seharusnya {$masterRm}).";
                }
            }
        } else {
            // Pegawai Baru: wajib menempati formasi jabatan aktif pada Master Formasi (unit, level, RM Band, kuota)
            [$unitExists, $validPositions] = $this->getUnitPositionData($data['unit_kerja'], $data['level']);

            if (! $unitExists) {
                $businessErrors[] = "Unit Kerja '{$data['unit_kerja']}' tidak terdaftar di Master Unit Organisasi.";
            } elseif ($validPositions === []) {
                $businessErrors[] = "Unit Kerja '{$data['unit_kerja']}' belum memiliki formasi jabatan untuk Level Strata '{$data['level']}'.";
            } else {
                $matchedPosition = $this->matchPosition($data['jabatan'], array_keys($validPositions));
                if (! $matchedPosition) {
                    $businessErrors[] = "Jabatan '{$data['jabatan']}' tidak valid untuk Unit Kerja '{$data['unit_kerja']}' dan Level Strata '{$data['level']}'.";
                } else {
                    $data['jabatan'] = $matchedPosition;
                    $seat = $validPositions[$matchedPosition];

                    if ($seat['rm_level'] !== null) {
                        if (blank($data['rm_level'] ?? null)) {
                            $data['rm_level'] = $seat['rm_level'];
                        } elseif ($data['rm_level'] !== $seat['rm_level']) {
                            $businessErrors[] = "RM Level '{$data['rm_level']}' tidak sesuai dengan formasi jabatan '{$matchedPosition}' (seharusnya {$seat['rm_level']}).";
                        }
                    }

                    $isDefinitif = ($data['status_penugasan'] ?? 'Definitif') === 'Definitif';
                    if ($isDefinitif && ! $seat['pool']) {
                        if ($this->occupancy($data['unit_kerja'], $matchedPosition) >= $seat['kuota']) {
                            $businessErrors[] = "Kuota formasi jabatan '{$matchedPosition}' pada Unit Kerja '{$data['unit_kerja']}' sudah terpenuhi ({$seat['kuota']} formasi).";
                        } elseif ($businessErrors === []) {
                            $this->occupancyCache[$data['unit_kerja']][$matchedPosition]++;
                        }
                    }
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
     * Mengambil data unit dan formasi jabatan aktif dengan In-Memory Memoization (O(1) lookup).
     *
     * @return array{0: bool, 1: array<string, array{rm_level: ?string, kuota: int, pool: bool}>}
     */
    public function getUnitPositionData(string $unitName, string $level): array
    {
        if (! isset($this->unitPositionsCache[$unitName])) {
            $unit = Unit::with(['positions' => function ($query) {
                $query->where('positions.is_active', true);
            }])->where('nama', $unitName)->where('is_active', true)->first();

            $positionsByLevel = [];
            foreach ($unit?->positions ?? [] as $pos) {
                $positionsByLevel[$pos->level][$pos->nama] = [
                    'rm_level' => $pos->rm_level,
                    'kuota' => (int) $pos->pivot->kuota,
                    'pool' => $pos->is_overflow || $pos->is_diperban,
                ];
            }

            $this->unitPositionsCache[$unitName] = [
                'exists' => $unit !== null,
                'positions' => $positionsByLevel,
            ];
        }

        $unitData = $this->unitPositionsCache[$unitName];

        return [$unitData['exists'], $unitData['positions'][$level] ?? []];
    }

    /**
     * Jumlah pemangku definitif pada formasi jabatan (unit, jabatan), termasuk baris baru dalam batch berjalan.
     */
    private function occupancy(string $unitName, string $jabatan): int
    {
        $this->occupancyCache[$unitName] ??= Employee::where('unit_kerja', $unitName)
            ->where('status_penugasan', 'Definitif')
            ->selectRaw('jabatan, count(*) as total')
            ->groupBy('jabatan')
            ->pluck('total', 'jabatan')
            ->map(fn ($total) => (int) $total)
            ->all();

        return $this->occupancyCache[$unitName][$jabatan] ??= 0;
    }

    /**
     * Pencocokan posisi toleran terhadap tanda baca dan spasi (delegasi ke PositionNameMatcher).
     */
    public function matchPosition(string $input, array $validPositions): ?string
    {
        return PositionNameMatcher::match($input, $validPositions);
    }
}
