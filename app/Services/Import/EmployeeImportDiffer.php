<?php

namespace App\Services\Import;

use App\Models\Employee;
use Carbon\Carbon;

/**
 * Pembanding data impor dengan data karyawan di basis data: rincian atribut yang berubah (status Perubahan) dan pemeriksaan
 * apakah baris identik (status Tetap). Identitas dan penempatan (NIK, jabatan, unit) tidak berubah lewat impor.
 */
class EmployeeImportDiffer
{
    public function __construct(
        private readonly ConcurrentJobTextParser $jobParser,
        private readonly EmployeeImportRowValidator $validator,
    ) {}

    /**
     * Data keluarga hanya tampil di halaman detail karyawan (DESIGN.md §11, UU PDP): pratinjau impor
     * hanya menandai bahwa nilainya berubah tanpa menampilkan isi lama maupun baru.
     */
    private const HIDDEN_DIFF_FIELDS = ['susunan_keluarga'];

    private const HIDDEN_VALUE = 'Diperbarui (data pribadi, tidak ditampilkan)';

    /**
     * Menghitung rincian atribut yang berubah (sebelum vs sesudah) untuk data status UPDATE.
     */
    public function diff(Employee $existing, array $newData): array
    {
        $diff = [];
        // Label rincian perubahan dari daftar kolom bersama; identitas dan penempatan (NIK, jabatan, unit) tidak berubah lewat impor.
        $labels = [];
        foreach (array_diff(EmployeeImportColumns::fields(EmployeeImportColumns::LAYOUT_TABULAR), ['nik', 'jabatan', 'unit_kerja']) as $field) {
            $labels[$field] = EmployeeImportColumns::label($field);
        }

        foreach ($labels as $field => $label) {
            if (! array_key_exists($field, $newData)) {
                continue;
            }

            $oldVal = trim((string) ($existing->{$field} ?? ''));
            $newVal = trim((string) ($newData[$field] ?? ''));

            if (in_array($field, ImportDateParser::FIELDS, true)) {
                $oldVal = $oldVal ? Carbon::parse($oldVal)->toDateString() : '';
                $newVal = $newVal ? Carbon::parse($newVal)->toDateString() : '';
            }

            if ($field === 'tugas_rangkap_penugasan_sementara') {
                if (empty($newVal)) {
                    continue;
                }
                if ($this->jobParser->matchesExisting($existing->nik, $newVal, $existing->unit_kerja)) {
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
     * Memeriksa apakah data karyawan dari Excel sama persis (identik) dengan data di database.
     */
    public function isIdentical(Employee $existing, array $newData): bool
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
            if (! $this->jobParser->matchesExisting($existing->nik, (string) $newData['tugas_rangkap_penugasan_sementara'], $existing->unit_kerja)) {
                return false;
            }
        }

        return true;
    }
}
