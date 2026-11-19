<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeDeletionArchive;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Permanent deletion of employee master records together with their stored profile photos.
 * Shared by the Daftar Karyawan single and bulk delete and the legacy bulk-destroy route.
 *
 * Each employee is archived (EmployeeDeletionArchive: master data, full placement history, actor, reason)
 * in the same transaction as the delete, so the job_histories audit trail survives the FK cascade.
 */
class EmployeeDeletionService
{
    public const REASON_MIN_LENGTH = 10;

    public const REASON_MAX_LENGTH = 500;

    /** Validation rules for the deletion reason, shared by every entry point. */
    public const REASON_RULES = ['required', 'string', 'min:'.self::REASON_MIN_LENGTH, 'max:'.self::REASON_MAX_LENGTH];

    /**
     * Deletes every employee the query returns, one model at a time so model events and cascades run.
     *
     * The rows are archived and deleted inside one transaction (joining the caller's transaction when there
     * is one). Photo files are removed only after the outermost transaction commits, so a failure leaves the
     * rows, the archive and the photos untouched, and only photos of rows actually deleted are removed.
     *
     * @return int Number of employees deleted.
     */
    public function deleteMany(Builder $employees, ?User $actor, string $reason): int
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new \InvalidArgumentException('Alasan penghapusan wajib diisi.');
        }

        return DB::transaction(function () use ($employees, $actor, $reason): int {
            $batchId = (string) Str::uuid();
            $count = 0;

            foreach ($employees->get() as $employee) {
                $photo = $employee->foto;

                $this->archive($employee, $batchId, $actor, $reason);
                $employee->delete();
                $count++;

                if ($photo) {
                    DB::afterCommit(function () use ($photo): void {
                        if (Storage::disk('public')->exists($photo)) {
                            Storage::disk('public')->delete($photo);
                        }
                    });
                }
            }

            return $count;
        });
    }

    private function archive(Employee $employee, string $batchId, ?User $actor, string $reason): void
    {
        EmployeeDeletionArchive::create([
            'batch_id' => $batchId,
            'employee_nik' => $employee->nik,
            'employee_nama' => $employee->nama,
            'jabatan' => $employee->jabatan,
            'unit_kerja' => $employee->unit_kerja,
            'employee_data' => $employee->attributesToArray(),
            'job_histories' => $employee->jobHistories()->get()->toArray(),
            'evaluations_count' => $employee->evaluations()->count(),
            'trainings_count' => $employee->trainings()->count(),
            'deleted_by' => $actor?->id,
            'deleted_by_name' => $actor?->name ?? 'Sistem',
            'reason' => $reason,
        ]);
    }
}
