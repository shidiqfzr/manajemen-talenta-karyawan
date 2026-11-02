<?php

namespace App\Services\Mutasi;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Services\Data\MutasiRequest;

/**
 * The only write path of a placement: mirrors it onto the employee master record
 * and appends the job history row (audit trail, SECURITY.md §5).
 */
class PlacementWriter
{
    /**
     * Single write path that mirrors a placement onto the employee master record.
     * Bypasses EmployeeObserver (which would otherwise open a duplicate history row).
     * Public so manual history maintenance reuses it instead of copying the payload.
     *
     * @param  array<string, mixed>  $attributes  rm_level, level, golongan, job_grade, status_penugasan, ...
     */
    public function apply(
        Employee $employee,
        string $jabatan,
        string $unitKerja,
        string $tmt,
        array $attributes = []
    ): void {
        $payload = array_merge([
            'jabatan' => $jabatan,
            'unit_kerja' => $unitKerja,
            'tanggal_dalam_jabatan' => $tmt,
            'tmt_unit_kerja' => $tmt,
        ], $attributes);

        // A missing job grade must never overwrite the one on record
        if (blank($payload['job_grade'] ?? null)) {
            unset($payload['job_grade']);
        }

        Employee::withoutEvents(fn () => $employee->update($payload));
    }

    /**
     * Record a new job history entry (audit trail, SECURITY.md §5).
     *
     * @param  array<string, mixed>  $profile  level, golongan, job_grade the employee holds in the new position
     * @param  array<string, mixed>  $overrides  columns that differ from the request (catatan, batch_id, ...)
     */
    public function recordHistory(
        Employee $employee,
        string $jabatan,
        string $unitKerja,
        MutasiRequest $request,
        array $profile,
        array $overrides = []
    ): JobHistory {
        return JobHistory::create(array_merge([
            'batch_id' => null,
            'chain_order' => null,
            'is_applied' => ! $request->isFuture(),
            'employee_nik' => $employee->nik,
            'created_by' => $request->createdBy,
            'jabatan' => $jabatan,
            'unit_kerja' => $unitKerja,
            'level' => $profile['level'] ?? $employee->level,
            'golongan' => $profile['golongan'] ?? $employee->golongan,
            'job_grade' => filled($profile['job_grade'] ?? null) ? (int) $profile['job_grade'] : $employee->job_grade,
            'tmt_awal' => $request->tmtAwal,
            'tmt_akhir' => null,
            'tmt_akhir_estimasi' => $request->tmtAkhirEstimasi,
            'tanggal_berakhir_sanksi' => $request->tanggalBerakhirSanksi,
            'status_penugasan' => $request->statusJabatan,
            'jenis_mutasi' => $request->jenisMutasi,
            'nomor_sk' => $request->nomorSk,
            'tanggal_sk' => $request->tanggalSk,
            'file_sk' => $request->fileSk,
            'catatan' => $request->catatan ?? $request->defaultCatatan(),
            'is_dispensasi' => $request->isDispensasi,
            'alasan_dispensasi' => $request->alasanDispensasi,
        ], $overrides));
    }
}
