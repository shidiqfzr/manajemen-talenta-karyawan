<?php

namespace App\Services\Mutasi;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\ManPowerPlanningService;

/**
 * Reads the formation (position) side of a mutation: who occupies / reserves a target position,
 * which positions an employee may be placed into, and the RM band of a position (DOMAIN.md §4.2 & §5.4).
 */
class FormationService
{
    public function __construct(private readonly ManPowerPlanningService $planning) {}

    /**
     * Analyse what will happen if an employee is moved to the given target position.
     * Returns a structured result that drives the live impact panel in the UI.
     */
    public function analyzeTarget(string $jabatan, string $unitKerja): array
    {
        // Check if the position is an overflow/advisor or diperban (pool) position
        $position = Position::getAllCached()->get($jabatan);
        $isPool = $position ? ($position->is_overflow || $position->is_diperban) : false;

        if ($isPool) {
            // Pools are infinitely vacant and should never trigger a Swap or Reservation Lock
            return [
                'type' => 'pool',
                'vacant' => true,
                'is_reserved' => false,
                'reserved_occupant' => null,
                'reserved_job' => null,
                'occupant' => null,
                'occupant_job' => null,
            ];
        }

        // Check if position is currently occupied by an active employee
        $occupant = Employee::with('currentJob')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->first();

        // Check if position is reserved by a scheduled future-dated mutation (is_applied = false)
        $pendingScheduled = JobHistory::with('employee')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->where('is_applied', false)
            ->latest('id')
            ->first();

        $isReserved = $pendingScheduled !== null;

        return [
            'type' => $occupant === null ? ($isReserved ? 'reserved' : 'vacant') : 'occupied',
            'vacant' => $occupant === null && ! $isReserved,
            'is_reserved' => $isReserved,
            'reserved_occupant' => $pendingScheduled?->employee,
            'reserved_job' => $pendingScheduled,
            'occupant' => $occupant,
            'occupant_job' => $occupant?->currentJob,
        ];
    }

    /**
     * Retrieve and filter available target positions for a specific employee and unit.
     */
    public function getAvailableTargetPositions(Employee $emp, string $targetUnitKerja, string $jenisMutasi): array
    {
        $unit = Unit::where('nama', $targetUnitKerja)->first();
        $allJabatan = [];

        if ($unit) {
            $allJabatan = $unit->positions()->active()->pluck('nama')->toArray();
        }

        if (empty($allJabatan)) {
            $historicalJobs = JobHistory::where('unit_kerja', $targetUnitKerja)
                ->pluck('jabatan')->unique()->toArray();
            $activeJobs = Employee::where('unit_kerja', $targetUnitKerja)
                ->pluck('jabatan')->unique()->toArray();

            $allJabatan = array_unique(array_merge($historicalJobs, $activeJobs));
            if (empty($allJabatan)) {
                $allJabatan = Position::getAllCached()->pluck('nama')->toArray();
            }
        }

        $empRmLevel = $this->planning->determineRmLevel($emp);
        $empRmNumber = self::rmNumber($empRmLevel);

        $filtered = collect();
        $positions = Position::getAllCached();
        $isTargetOperational = $unit ? $unit->isOperationalUnit() : false; // Fallback to false if unit is not found (though it should be)

        foreach ($allJabatan as $j) {
            $jRmNumber = self::rmNumber($this->resolvePositionRm($j));

            if ($jenisMutasi === 'PROMOSI') {
                if ($jRmNumber < $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'DEMOSI') {
                if ($jRmNumber > $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif (in_array($jenisMutasi, ['ROTASI', 'MUTASI_UNIT'])) {
                if ($jRmNumber === $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'SANKSI_INDISIPLINER') {
                if (isset($positions[$j]) && $positions[$j]->is_diperban && $jRmNumber === $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'PENUGASAN_KHUSUS') {
                if (isset($positions[$j]) && $positions[$j]->is_overflow && ! $positions[$j]->is_diperban && $jRmNumber === $empRmNumber) {
                    $filtered->push($j);
                }
            } elseif ($jenisMutasi === 'PENUGASAN') {
                $filtered->push($j);
            }
        }

        // SMART OVERRIDE FOR POOLS (in-memory filtering from cached master)
        if (in_array($jenisMutasi, ['SANKSI_INDISIPLINER', 'PENUGASAN_KHUSUS'])) {
            $isSanction = $jenisMutasi === 'SANKSI_INDISIPLINER';
            $poolPositions = $positions->filter(
                fn ($pos) => $isSanction ? $pos->is_diperban : $pos->is_overflow
            );

            foreach ($poolPositions as $pos) {
                if (! $isSanction && $pos->is_diperban) {
                    continue;
                }

                $posRm = self::rmNumber($pos->rm_level);
                if ($posRm === $empRmNumber) {
                    $posNameLower = strtolower($pos->nama);

                    // Domain rules
                    $isTanamanPos = str_contains($posNameLower, 'tanaman');
                    $isTanamanUnit = str_contains(strtolower($targetUnitKerja), 'tanaman') || str_contains(strtolower($targetUnitKerja), 'kebun') || str_contains(strtolower($targetUnitKerja), 'group');
                    if ($isTanamanPos && ! $isTanamanUnit) {
                        continue;
                    }

                    $isTeknikPos = str_contains($posNameLower, 'teknik') || str_contains($posNameLower, 'pabrik');
                    $isTeknikUnit = str_contains(strtolower($targetUnitKerja), 'teknik') || str_contains(strtolower($targetUnitKerja), 'pabrik') || str_contains(strtolower($targetUnitKerja), 'group');
                    if ($isTeknikPos && ! $isTeknikUnit) {
                        continue;
                    }

                    // Nomenclature rules
                    if ($pos->is_diperban && $posRm < 4) {
                        if ($isTargetOperational && ! $pos->isOperationalTitle()) {
                            continue;
                        }
                        if (! $isTargetOperational && ! $pos->isKantorTitle()) {
                            continue;
                        }
                    }

                    if (! $filtered->contains($pos->nama)) {
                        $filtered->push($pos->nama);
                    }
                }
            }
        }

        return $filtered->sort()->values()->toArray();
    }

    /**
     * Resolve the RM band of a position in canonical 'RM-n' format (DOMAIN.md §4.2).
     * Master positions take precedence; otherwise falls back to job title matching.
     */
    public function resolvePositionRm(string $jabatan): string
    {
        $position = Position::getAllCached()->get($jabatan);
        if ($position && $position->rm_level) {
            return $position->rm_level;
        }

        return 'RM-'.$this->planning->determineRmLevelByJobTitle($jabatan);
    }

    /**
     * Derive Karpim (RM-1..RM-3) vs Karpel (RM-4) classification per DOMAIN.md §4.1.
     */
    public function deriveLevelForRm(string $rmLevel, ?string $fallback = null): ?string
    {
        return match ($rmLevel) {
            'RM-4' => 'Karpel',
            'RM-1', 'RM-2', 'RM-3' => 'Karpim',
            default => $fallback,
        };
    }

    public function isPoolPosition(string $jabatan): bool
    {
        $position = Position::getAllCached()->get($jabatan);

        return $position ? ($position->is_overflow || $position->is_diperban) : false;
    }

    /**
     * Parse numeric rank from an RM string (e.g. 'RM-3' -> 3).
     */
    public static function rmNumber(?string $rmLevel): ?int
    {
        if (! $rmLevel) {
            return null;
        }

        return (int) str_replace('RM-', '', $rmLevel);
    }

    /**
     * Pool flags of a master position (Advisor = overflow, Diperbantukan = diperban).
     *
     * @return array{is_diperban: bool, is_overflow: bool}
     */
    public function getPositionFlags(string $jabatan): array
    {
        $position = Position::getAllCached()->get($jabatan);

        return [
            'is_diperban' => (bool) $position?->is_diperban,
            'is_overflow' => (bool) $position?->is_overflow,
        ];
    }

    /** RM band (RM-1..RM-4) of an employee in canonical format. */
    public function resolveEmployeeRm(Employee $employee): string
    {
        return $this->planning->determineRmLevel($employee);
    }
}
