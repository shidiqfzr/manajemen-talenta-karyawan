<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanent deletion of employee master records together with their stored profile photos.
 * Shared by the Direktori Pegawai bulk action and the legacy bulk-destroy route.
 */
class EmployeeDeletionService
{
    /**
     * Deletes every employee the query returns, one model at a time so model events and cascades run.
     *
     * The rows are deleted inside one transaction (joining the caller's transaction when there is one).
     * Photo files are removed only after the outermost transaction commits, so a failure leaves both
     * the rows and their photos intact, and only photos of rows actually deleted are removed.
     *
     * @return int Number of employees deleted.
     */
    public function deleteMany(Builder $employees): int
    {
        return DB::transaction(function () use ($employees): int {
            $count = 0;

            foreach ($employees->get() as $employee) {
                $photo = $employee->foto;

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
}
