<?php

namespace App\Observers;

use App\Models\Employee;
use App\Models\JobHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeObserver
{
    /** Fields considered core “job identity” */
    private const CORE_FIELDS = ['jabatan', 'unit_kerja', 'level', 'golongan'];

    /** Fields considered start-date sources */
    private const DATE_FIELDS = ['tanggal_dalam_jabatan', 'tmt_unit_kerja'];

    /**
     * On first create, seed 1 active history row (if job info is present).
     */
    public function created(Employee $employee): void
    {
        if (!$this->hasCoreJob($employee)) {
            return; // skip if no meaningful job info yet
        }

        // Don't duplicate if history has been seeded already (e.g., import created manually)
        if ($employee->jobHistories()->exists()) {
            return;
        }

        $start = $this->resolveStartDate($employee);

        JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan'      => $employee->jabatan,
            'unit_kerja'   => $employee->unit_kerja,
            'level'        => $employee->level,
            'golongan'     => $employee->golongan,
            'tmt_awal'     => $start->toDateString(),
            'tmt_akhir'    => null,
            'jenis_mutasi' => null,
        ]);
    }

    /**
     * When job fields change, close current and open a new history.
     * When only dates change, adjust the active row's start date.
     */
    public function updated(Employee $employee): void
    {
        if (!$this->hasCoreJob($employee)) {
            return;
        }

        $dirty = $employee->getDirty();

        $coreChanged = collect(self::CORE_FIELDS)
            ->some(fn ($f) => array_key_exists($f, $dirty));

        $dateChangedOnly = !$coreChanged && collect(self::DATE_FIELDS)
            ->some(fn ($f) => array_key_exists($f, $dirty));

        if (!$coreChanged && !$dateChangedOnly) {
            return; // nothing relevant changed
        }

        $newStart = $this->resolveStartDate($employee);

        DB::transaction(function () use ($employee, $coreChanged, $dateChangedOnly, $newStart) {
            $active = $employee->jobHistories()
                ->whereNull('tmt_akhir')
                ->latest('tmt_awal')
                ->first();

            if ($dateChangedOnly) {
                // Only start date changed → just adjust the active row if present
                if ($active) {
                    $active->tmt_awal = $newStart->toDateString();
                    $active->save();
                }
                return;
            }

            // Core fields changed → close current and open new
            if ($active) {
                $proposedEnd = $newStart->copy()->subDay();
                // Never end before the recorded start
                if ($proposedEnd->lt(Carbon::parse($active->tmt_awal))) {
                    $proposedEnd = Carbon::parse($active->tmt_awal);
                }
                $active->tmt_akhir = $proposedEnd->toDateString();
                $active->save();
            }

            JobHistory::create([
                'employee_nik' => $employee->nik,
                'jabatan'      => $employee->jabatan,
                'unit_kerja'   => $employee->unit_kerja,
                'level'        => $employee->level,
                'golongan'     => $employee->golongan,
                'tmt_awal'     => $newStart->toDateString(),
                'tmt_akhir'    => null,
                'jenis_mutasi' => null,
            ]);
        });
    }

    /* ---------------------------- Helpers ---------------------------- */

    /** True if employee has minimal core job info to record history */
    private function hasCoreJob(Employee $e): bool
    {
        return filled($e->jabatan) && filled($e->unit_kerja);
    }

    /** Resolve start date from preferred fields or fallback to today */
    private function resolveStartDate(Employee $e): Carbon
    {
        $candidates = [
            $e->getAttribute('tanggal_dalam_jabatan'),
            $e->getAttribute('tmt_unit_kerja'),
        ];

        foreach ($candidates as $value) {
            if ($value instanceof Carbon) return $value->copy()->startOfDay();
            if (!empty($value))       return Carbon::parse($value)->startOfDay();
        }

        return now()->startOfDay();
    }

    /**
     * Handle the Employee "deleted" event.
     */
    public function deleted(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "restored" event.
     */
    public function restored(Employee $employee): void
    {
        //
    }

    /**
     * Handle the Employee "force deleted" event.
     */
    public function forceDeleted(Employee $employee): void
    {
        //
    }
}
