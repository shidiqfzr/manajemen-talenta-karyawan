<?php

namespace App\Services\Mutasi;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use App\Services\JobVacancyService;
use App\Services\ManPowerPlanningService;
use Illuminate\Support\Facades\DB;

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
     *
     * `$excludeNiks` are the employees whose own scheduled bookings must not count against the formasi (the requester,
     * or every participant of a chain): a booking by someone who is moving anyway does not take a seat from them.
     *
     * @param  list<string>  $excludeNiks
     */
    public function analyzeTarget(string $jabatan, string $unitKerja, array $excludeNiks = []): array
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
                'kuota' => null,
                'holders' => 0,
                'free_slots' => null,
                'open_slots' => null,
                'multi_seat' => false,
            ];
        }

        // Seat count of the formasi: the master quota (0 is a real quota, meaning no seat) or one seat for a pair outside the master.
        $quotaRow = DB::table('position_unit')
            ->join('units', 'units.id', '=', 'position_unit.unit_id')
            ->join('positions', 'positions.id', '=', 'position_unit.position_id')
            ->where('units.nama', $unitKerja)
            ->where('positions.nama', $jabatan)
            ->first(['position_unit.kuota']);
        $kuota = $quotaRow === null ? 1 : (int) $quotaRow->kuota;

        // Every current holder, Pj/Plt included: a temporary holder keeps the seat, so the formasi is not released (§5.2).
        $holders = Employee::with('currentJob')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->orderBy('nik')
            ->get();
        // A Pj/Plt placed through Mutasi has no Employee row here but still takes a seat (open history row).
        $temporaryHolders = app(JobVacancyService::class)->temporaryHolderCounts($jabatan, $unitKerja)->get("{$unitKerja}|{$jabatan}", 0);
        $holderCount = $holders->count() + $temporaryHolders;
        $freeSlots = JobVacancyService::freeSlots($kuota, $holderCount);
        $hasFreeSlot = $freeSlots > 0;

        // Scheduled future-dated mutations (is_applied = false) reserve seats; the latest one is reported.
        $pending = JobHistory::with('employee')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->where('is_applied', false)
            ->when($excludeNiks !== [], fn ($query) => $query->whereNotIn('employee_nik', $excludeNiks))
            ->orderByDesc('id')
            ->get();
        $pendingScheduled = $pending->first();

        // Bookings only block the formasi once they use up the free quota (always so for a single seat).
        $isReserved = JobVacancyService::isReserved($pending->count(), $freeSlots);
        $openSlots = max($freeSlots - $pending->count(), 0);

        // A formasi with a free seat takes a straight transfer; only a full one needs the occupant to swap out.
        $occupant = $hasFreeSlot ? null : $holders->first();

        return [
            'type' => $hasFreeSlot ? ($isReserved ? 'reserved' : 'vacant') : 'occupied',
            'vacant' => $hasFreeSlot && ! $isReserved,
            'is_reserved' => $isReserved,
            'reserved_occupant' => $pendingScheduled?->employee,
            'reserved_job' => $pendingScheduled,
            'occupant' => $occupant,
            'occupant_job' => $occupant?->currentJob,
            'kuota' => $kuota,
            'holders' => $holderCount,
            'free_slots' => $freeSlots,
            'open_slots' => $openSlots,
            'multi_seat' => $kuota > 1,
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
            // Jabatan penampungan yang dibuka pada unit bukan target mutasi biasa; hanya Penugasan Khusus dan Sanksi
            // yang menjangkaunya (blok "smart override" di bawah).
            $allJabatan = $unit->positions()->active()
                ->where('positions.is_overflow', false)
                ->where('positions.is_diperban', false)
                ->pluck('nama')->toArray();
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
     * Derive Karpim (RM-1..RM-3) vs Karpel (RM-4..RM-7) classification per DOMAIN.md §4.1.
     */
    public function deriveLevelForRm(string $rmLevel, ?string $fallback = null): ?string
    {
        return match ($rmLevel) {
            'RM-4', 'RM-5', 'RM-6', 'RM-7' => 'Karpel',
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

    /** RM band (RM-1..RM-7) of an employee in canonical format. */
    public function resolveEmployeeRm(Employee $employee): string
    {
        return $this->planning->determineRmLevel($employee);
    }
}
