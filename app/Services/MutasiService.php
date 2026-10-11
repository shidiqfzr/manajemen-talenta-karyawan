<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Models\Unit;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MutasiService
{
    // -------------------------------------------------------------------------
    // Analysis
    // -------------------------------------------------------------------------

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
            // Pools are infinitely vacant and should never trigger a Swap
            return [
                'type' => 'pool',
                'vacant' => true,
                'occupant' => null,
                'occupant_job' => null,
            ];
        }

        $occupant = Employee::with('currentJob')
            ->where('jabatan', $jabatan)
            ->where('unit_kerja', $unitKerja)
            ->first();

        return [
            'type' => $occupant === null ? 'vacant' : 'occupied',
            'vacant' => $occupant === null,
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
        } else {
            $historicalJobs = JobHistory::where('unit_kerja', $targetUnitKerja)
                ->pluck('jabatan')->unique()->toArray();
            $activeJobs = Employee::where('unit_kerja', $targetUnitKerja)
                ->pluck('jabatan')->unique()->toArray();

            $allJabatan = array_unique(array_merge($historicalJobs, $activeJobs));
            if (empty($allJabatan)) {
                $allJabatan = app(ManPowerPlanningService::class)->getKnownJabatan()->toArray();
            }
        }

        $empRmLevel = app(ManPowerPlanningService::class)->determineRmLevel($emp);
        $empRmNumber = $this->parseRmNumber($empRmLevel);

        $filtered = collect();
        $positions = Position::getAllCached();
        $isTargetOperational = $unit ? $unit->isOperationalUnit() : false; // Fallback to false if unit is not found (though it should be)

        foreach ($allJabatan as $j) {
            $jRmNumber = null;
            if (isset($positions[$j]) && $positions[$j]->rm_level) {
                $jRmNumber = $this->parseRmNumber($positions[$j]->rm_level);
            } else {
                $jRmNumber = app(ManPowerPlanningService::class)->determineRmLevelByJobTitle($j);
            }

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

                $posRm = $this->parseRmNumber($pos->rm_level);
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

    // -------------------------------------------------------------------------
    // Core Mutasi — single entry point for ALL transfer types
    // -------------------------------------------------------------------------

    /**
     * Process any position change atomically.
     *
     * Auto-detects:
     *  - Target VACANT  → straight transfer (creates 1 chain vacancy at employee's old position)
     *  - Target OCCUPIED → swap (both employees exchange positions, zero net vacancies)
     *
     * Returns an array describing the result for the success flash message.
     *
     * @throws \InvalidArgumentException if employee is already at the target position
     * @throws \RuntimeException if target occupant has no active job (edge-case guard)
     */
    public function processMutasi(
        string $employeeNik,
        string $targetJabatan,
        string $targetUnitKerja,
        string $tmtAwal,
        string $jenisMutasi = 'MUTASI_UNIT',
        ?string $nomorSk = null,
        ?string $tanggalSk = null,
        ?string $catatan = null,
        ?string $tanggalBerakhirSanksi = null,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null
    ): array {
        return DB::transaction(function () use (
            $employeeNik, $targetJabatan, $targetUnitKerja,
            $tmtAwal, $jenisMutasi, $nomorSk, $tanggalSk, $catatan, $tanggalBerakhirSanksi, $statusJabatan, $tmtAkhirEstimasi
        ) {
            $employee = Employee::with('currentJob')
                ->where('nik', $employeeNik)
                ->firstOrFail();

            // Guard: same position
            if (
                $employee->jabatan === $targetJabatan &&
                $employee->unit_kerja === $targetUnitKerja
            ) {
                throw new \InvalidArgumentException(
                    "Karyawan {$employee->nama} sudah berada di posisi {$targetJabatan} di {$targetUnitKerja}."
                );
            }

            $tmtAkhir = Carbon::parse($tmtAwal)->subDay()->toDateString();
            $analysis = $this->analyzeTarget($targetJabatan, $targetUnitKerja);

            if ($analysis['vacant']) {
                // ── Straight transfer ─────────────────────────────────────
                $this->doTransfer(
                    $employee, $targetJabatan, $targetUnitKerja,
                    $tmtAwal, $tmtAkhir, $jenisMutasi, $nomorSk, $tanggalSk, $catatan, $tanggalBerakhirSanksi, $statusJabatan, $tmtAkhirEstimasi
                );

                return [
                    'type' => 'transfer',
                    'employee' => $employee->nama,
                    'to_jabatan' => $targetJabatan,
                    'to_unit' => $targetUnitKerja,
                    'displaced' => null,
                ];
            } else {
                // ── Swap (target occupied) ────────────────────────────────
                $occupant = $analysis['occupant'];

                if (! $occupant->currentJob) {
                    throw new \RuntimeException(
                        "Karyawan {$occupant->nama} yang menempati posisi tersebut tidak memiliki riwayat jabatan aktif."
                    );
                }

                $this->doSwap(
                    $employee, $occupant, $targetJabatan, $targetUnitKerja,
                    $tmtAwal, $tmtAkhir, $jenisMutasi, $nomorSk, $tanggalSk, $catatan, $tanggalBerakhirSanksi, $statusJabatan, $tmtAkhirEstimasi
                );

                return [
                    'type' => 'swap',
                    'employee' => $employee->nama,
                    'to_jabatan' => $targetJabatan,
                    'to_unit' => $targetUnitKerja,
                    'displaced' => $occupant->nama,
                ];
            }
        });
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    /** Move one employee to a (currently vacant) target position. */
    private function doTransfer(
        Employee $employee,
        string $targetJabatan,
        string $targetUnitKerja,
        string $tmtAwal,
        string $tmtAkhir,
        string $jenisMutasi,
        ?string $nomorSk,
        ?string $tanggalSk,
        ?string $catatan,
        ?string $tanggalBerakhirSanksi = null,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null
    ): void {
        $isDefinitif = $statusJabatan === 'Definitif';

        if ($isDefinitif) {
            // 1. Close current definitive job history
            $employee->currentJob?->update(['tmt_akhir' => $tmtAkhir]);

            // 2. Update employee profile (bypass observer to avoid double history row)
            Employee::withoutEvents(function () use ($employee, $targetJabatan, $targetUnitKerja, $tmtAwal, $tanggalBerakhirSanksi) {
                $employee->update([
                    'jabatan' => $targetJabatan,
                    'unit_kerja' => $targetUnitKerja,
                    'tanggal_dalam_jabatan' => $tmtAwal,
                    'tmt_unit_kerja' => $tmtAwal,
                    'tanggal_berakhir_sanksi' => $tanggalBerakhirSanksi,
                ]);
            });
        }

        // 3. Create new active job history
        $this->recordJobHistory(
            employee: $employee,
            jabatan: $targetJabatan,
            unitKerja: $targetUnitKerja,
            tmtAwal: $tmtAwal,
            jenisMutasi: $jenisMutasi,
            nomorSk: $nomorSk,
            tanggalSk: $tanggalSk,
            catatan: $catatan ?? "Mutasi ke {$targetJabatan} di {$targetUnitKerja}",
            statusJabatan: $statusJabatan,
            tmtAkhirEstimasi: $tmtAkhirEstimasi
        );
    }

    /**
     * Atomically swap two employees' positions.
     * Creates ZERO net vacancies — both old histories close and two new ones open simultaneously.
     */
    private function doSwap(
        Employee $empA,
        Employee $empB,
        string $targetJabatan,  // empA's target (= empB's current position)
        string $targetUnitKerja,
        string $tmtAwal,
        string $tmtAkhir,
        string $jenisMutasi,
        ?string $nomorSk,
        ?string $tanggalSk,
        ?string $catatan,
        ?string $tanggalBerakhirSanksi = null,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null
    ): void {
        // Snapshot both positions BEFORE touching anything
        $posA = [
            'jabatan' => $empA->jabatan,
            'unit_kerja' => $empA->unit_kerja,
            'level' => $empA->level,
            'golongan' => $empA->golongan,
        ];
        $posB = [
            'jabatan' => $empB->jabatan,
            'unit_kerja' => $empB->unit_kerja,
            'level' => $empB->level,
            'golongan' => $empB->golongan,
        ];

        $isDefinitif = $statusJabatan === 'Definitif';

        // 1. Close active job histories (if Definitif, close empA's definitive job)
        if ($isDefinitif) {
            $empA->currentJob?->update(['tmt_akhir' => $tmtAkhir]);
        }
        $empB->currentJob?->update(['tmt_akhir' => $tmtAkhir]);

        // 2. Swap employee profiles atomically (bypass observer)
        Employee::withoutEvents(function () use ($empA, $empB, $posA, $posB, $tmtAwal, $tanggalBerakhirSanksi, $isDefinitif) {
            if ($isDefinitif) {
                $empA->update([
                    'jabatan' => $posB['jabatan'],
                    'unit_kerja' => $posB['unit_kerja'],
                    'tanggal_dalam_jabatan' => $tmtAwal,
                    'tmt_unit_kerja' => $tmtAwal,
                    'tanggal_berakhir_sanksi' => $tanggalBerakhirSanksi,
                ]);
            }
            $empB->update([
                'jabatan' => $posA['jabatan'],
                'unit_kerja' => $posA['unit_kerja'],
                'tanggal_dalam_jabatan' => $tmtAwal,
                'tmt_unit_kerja' => $tmtAwal,
            ]);
        });

        // 3. Create two new active job history rows (cross-swapped)
        $baseNote = $catatan ?? 'Tukar jabatan / rotasi bersilang';
        $this->recordJobHistory(
            employee: $empA,
            jabatan: $posB['jabatan'],
            unitKerja: $posB['unit_kerja'],
            tmtAwal: $tmtAwal,
            jenisMutasi: $jenisMutasi,
            nomorSk: $nomorSk,
            tanggalSk: $tanggalSk,
            catatan: "{$baseNote} — bertukar dengan {$empB->nama}",
            statusJabatan: $statusJabatan,
            tmtAkhirEstimasi: $tmtAkhirEstimasi
        );
        $this->recordJobHistory(
            employee: $empB,
            jabatan: $posA['jabatan'],
            unitKerja: $posA['unit_kerja'],
            tmtAwal: $tmtAwal,
            jenisMutasi: $jenisMutasi,
            nomorSk: $nomorSk,
            tanggalSk: $tanggalSk,
            catatan: "{$baseNote} — bertukar dengan {$empA->nama}",
            statusJabatan: $statusJabatan,
            tmtAkhirEstimasi: $tmtAkhirEstimasi
        );
    }

    /**
     * Record a new job history entry.
     */
    private function recordJobHistory(
        Employee $employee,
        string $jabatan,
        string $unitKerja,
        string $tmtAwal,
        string $jenisMutasi,
        ?string $nomorSk,
        ?string $tanggalSk,
        string $catatan,
        string $statusJabatan = 'Definitif',
        ?string $tmtAkhirEstimasi = null
    ): JobHistory {
        return JobHistory::create([
            'employee_nik' => $employee->nik,
            'jabatan' => $jabatan,
            'unit_kerja' => $unitKerja,
            'level' => $employee->level,
            'golongan' => $employee->golongan,
            'tmt_awal' => $tmtAwal,
            'tmt_akhir' => null,
            'tmt_akhir_estimasi' => $tmtAkhirEstimasi,
            'status_penugasan' => $statusJabatan,
            'jenis_mutasi' => $jenisMutasi,
            'nomor_sk' => $nomorSk,
            'tanggal_sk' => $tanggalSk,
            'catatan' => $catatan,
        ]);
    }

    /**
     * Parse numeric rank from an RM string (e.g. 'RM-3' -> 3).
     */
    private function parseRmNumber(?string $rmLevel): ?int
    {
        if (! $rmLevel) {
            return null;
        }

        return (int) str_replace('RM-', '', $rmLevel);
    }

    // -------------------------------------------------------------------------
    // History / Reporting
    // -------------------------------------------------------------------------

    /**
     * Paginated system-wide mutation history (all job_histories, newest first).
     */
    public function getMutasiHistory(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $jenisMutasi = null,
        int $perPage = 15,
    ): LengthAwarePaginator {
        return JobHistory::with('employee')
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q2) use ($search) {
                    $q2->where('jabatan', 'like', "%{$search}%")
                        ->orWhere('unit_kerja', 'like', "%{$search}%")
                        ->orWhereHas('employee', fn ($q3) => $q3->where('nama', 'like', "%{$search}%")
                            ->orWhere('nik', 'like', "%{$search}%")
                        );
                });
            })
            ->when($unitKerja, fn ($q) => $q->where('unit_kerja', $unitKerja))
            ->when($jenisMutasi, fn ($q) => $q->where('jenis_mutasi', $jenisMutasi))
            ->orderByDesc('tmt_awal')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /**
     * All distinct jabatan values known in the system (for dropdowns).
     */
    public function getKnownJabatan(): Collection
    {
        $histories = DB::table('job_histories')
            ->select('jabatan')->distinct()
            ->orderBy('jabatan')->pluck('jabatan')->toArray();
        $master = Position::pluck('nama')->toArray();

        return collect(array_unique(array_merge($histories, $master)))->sort()->values();
    }

    /**
     * All distinct unit_kerja values known in the system (for dropdowns).
     */
    public function getKnownUnits(): Collection
    {
        $histories = DB::table('job_histories')
            ->select('unit_kerja')->distinct()
            ->orderBy('unit_kerja')->pluck('unit_kerja')->toArray();

        $master = array_unique(array_merge(...\array_values(Employee::GROUPED_UNITS)));

        return collect(array_unique(array_merge($histories, $master)))->sort()->values();
    }
}
