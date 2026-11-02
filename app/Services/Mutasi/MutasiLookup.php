<?php

namespace App\Services\Mutasi;

use App\Models\Employee;
use App\Models\Position;
use App\Models\Unit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lookups that feed the mutation screens (candidates, units, positions, compliance previews),
 * so Livewire components never query Eloquent directly.
 */
class MutasiLookup
{
    public function __construct(
        private readonly FormationService $formations,
        private readonly MutasiGovernanceGuard $guard,
        private readonly TalentProfileBuilder $profiles,
    ) {}

    /**
     * Employees offered as mutation candidates: not past retirement age (BUP), optionally of one unit.
     */
    public function getSelectableEmployees(?string $unitKerja = null): Collection
    {
        return Employee::query()
            ->where(fn ($q) => $q->whereNull('tanggal_pensiun')->orWhere('tanggal_pensiun', '>=', now()->startOfMonth()))
            ->when($unitKerja, fn ($q) => $q->where('unit_kerja', $unitKerja))
            ->orderBy('nama')
            ->get(['nik', 'nama', 'jabatan', 'unit_kerja', 'rm_level', 'golongan']);
    }

    /**
     * Active unit names grouped by wilayah for the unit combobox (falls back to the static master list).
     *
     * @return array<string, list<string>>
     */
    public function getGroupedUnits(): array
    {
        $activeUnits = Unit::where('is_active', true)->orderBy('urutan')->orderBy('nama')->get();

        if ($activeUnits->isEmpty()) {
            return Employee::GROUPED_UNITS;
        }

        return $activeUnits->groupBy('wilayah')
            ->map(fn ($units) => $units->pluck('nama')->toArray())
            ->toArray();
    }

    public function findEmployee(string $nik): ?Employee
    {
        return Employee::where('nik', $nik)->first();
    }

    /**
     * Target positions an employee may be placed into for the given unit & mutation type.
     */
    public function listTargetPositions(string $nik, string $targetUnitKerja, string $jenisMutasi): array
    {
        $employee = $this->findEmployee($nik);

        return $employee ? $this->formations->getAvailableTargetPositions($employee, $targetUnitKerja, $jenisMutasi) : [];
    }

    /**
     * Unit tenure compliance of an employee by NIK (null when the employee does not exist).
     */
    public function tenureComplianceFor(string $nik, string $targetUnitKerja): ?array
    {
        $employee = $this->findEmployee($nik);

        return $employee ? $this->guard->checkTenureCompliance($employee, $targetUnitKerja) : null;
    }

    /**
     * Promotion eligibility of an employee by NIK (null when the employee does not exist).
     */
    public function promotionEligibilityFor(string $nik, string $targetJabatan): ?array
    {
        $employee = $this->findEmployee($nik);

        return $employee ? $this->guard->checkPromotionEligibility($employee, $targetJabatan) : null;
    }

    /**
     * Lightweight employee snapshot used to fill a chain-mutation node (null when the employee does not exist).
     */
    public function getEmployeeSnapshot(string $nik): ?array
    {
        $employee = $this->findEmployee($nik);
        if (! $employee) {
            return null;
        }

        return [
            'nik' => $employee->nik,
            'nama' => $employee->nama,
            'jabatan' => $employee->jabatan,
            'unit_kerja' => $employee->unit_kerja,
            'rm_level' => $this->formations->resolveEmployeeRm($employee),
            'golongan' => $employee->golongan,
            'job_grade' => $employee->job_grade,
            'profile' => $this->profiles->build($employee->nik),
        ];
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
