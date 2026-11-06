<?php

namespace App\Services;

use App\Exceptions\MutasiIntegrityException;
use App\Exceptions\MutasiRuleViolationException;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Unit;
use App\Services\Data\MutasiRequest;
use App\Services\Mutasi\ChainTopologyAnalyzer;
use App\Services\Mutasi\FormationService;
use App\Services\Mutasi\MutasiGovernanceGuard;
use App\Services\Mutasi\MutasiHistoryReader;
use App\Services\Mutasi\MutasiLookup;
use App\Services\Mutasi\PlacementWriter;
use App\Services\Mutasi\ScheduledMutationService;
use App\Services\Mutasi\TalentProfileBuilder;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Entry point of the mutation module (DOMAIN.md §5.2 & §9.2).
 *
 * Orchestrates the three transaction modes — straight transfer, swap and collective chain —
 * and delegates analysis, governance, scheduling, history and lookups to the collaborators
 * in App\Services\Mutasi. Callers keep using this class; the delegating methods below are its public API.
 */
class MutasiService
{
    public function __construct(
        private readonly FormationService $formations,
        private readonly MutasiGovernanceGuard $guard,
        private readonly ChainTopologyAnalyzer $chains,
        private readonly PlacementWriter $placement,
        private readonly ScheduledMutationService $scheduled,
        private readonly MutasiHistoryReader $history,
        private readonly TalentProfileBuilder $profiles,
        private readonly MutasiLookup $lookup,
    ) {}

    // -------------------------------------------------------------------------
    // Core Mutasi — straight transfer, swap & collective chain
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
     * @throws MutasiRuleViolationException if a governance rule is violated or the employee is already at the target
     * @throws MutasiIntegrityException if target occupant has no active job (edge-case guard)
     */
    public function processMutasi(MutasiRequest $request): array
    {
        return DB::transaction(function () use ($request) {
            // Serialize concurrent mutations into the same target unit (prevents double-filling a vacancy)
            Unit::where('nama', $request->targetUnitKerja)->lockForUpdate()->first();

            $employee = Employee::with('currentJob')
                ->where('nik', $request->employeeNik)
                ->lockForUpdate()
                ->firstOrFail();

            // Guard: same position
            if ($employee->jabatan === $request->targetJabatan && $employee->unit_kerja === $request->targetUnitKerja) {
                throw new MutasiRuleViolationException(
                    "Karyawan {$employee->nama} sudah berada di posisi {$request->targetJabatan} di {$request->targetUnitKerja}."
                );
            }

            $this->guard->assertAllowed($employee, $request);

            $analysis = $this->formations->analyzeTarget($request->targetJabatan, $request->targetUnitKerja);
            $this->assertTargetNotReservedByOthers($analysis, $request);

            if ($analysis['vacant'] || $analysis['type'] === 'pool') {
                // ── Straight transfer ─────────────────────────────────────
                $this->doTransfer($employee, $request);

                return $this->mutasiResult('transfer', $employee, $request);
            }

            // ── Swap (target occupied) ────────────────────────────────
            $occupant = $this->resolveSwapOccupant($employee, $analysis, $request);
            $this->doSwap($employee, $occupant, $request);

            return $this->mutasiResult('swap', $employee, $request, $occupant->nama);
        });
    }

    /**
     * Guard: the target formation may be reserved by a scheduled mutation of someone else.
     *
     * @throws MutasiRuleViolationException
     */
    private function assertTargetNotReservedByOthers(array $analysis, MutasiRequest $request): void
    {
        if (! $analysis['is_reserved'] || ($analysis['reserved_job']->employee_nik ?? null) === $request->employeeNik) {
            return;
        }

        $resName = $analysis['reserved_occupant']->nama ?? $analysis['reserved_job']->employee_nik;
        $resTmt = $analysis['reserved_job']->tmt_awal ? Carbon::parse($analysis['reserved_job']->tmt_awal)->translatedFormat('d F Y') : 'mendatang';

        throw new MutasiRuleViolationException(
            "Formasi {$request->targetJabatan} di {$request->targetUnitKerja} telah dipesan (Reserved) oleh mutasi terjadwal atas nama {$resName} (TMT aktif: {$resTmt}). Pilih formasi lain atau batalkan jadwal mutasi tersebut."
        );
    }

    /**
     * Lock and validate the occupant that employee A will swap with (DOMAIN.md §5.2.2).
     *
     * @throws MutasiRuleViolationException|MutasiIntegrityException
     */
    private function resolveSwapOccupant(Employee $employee, array $analysis, MutasiRequest $request): Employee
    {
        // A Pj/Plt assignee keeps their definitive seat, so the occupant would have nowhere to go.
        if (! $request->isDefinitif()) {
            throw new MutasiRuleViolationException(
                "Penugasan sementara ({$request->statusJabatan}) hanya dapat ditempatkan pada formasi kosong. Formasi {$request->targetJabatan} di {$request->targetUnitKerja} saat ini diduduki oleh {$analysis['occupant']->nama}."
            );
        }

        $occupant = Employee::with('currentJob')
            ->whereKey($analysis['occupant']->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        if (! $occupant->currentJob) {
            throw new MutasiIntegrityException(
                "Karyawan {$occupant->nama} yang menempati posisi tersebut tidak memiliki riwayat jabatan aktif."
            );
        }

        // Guard: Lateral Swap Only (DOMAIN.md §5.2.2 & Enterprise Governance)
        $empARm = $this->formations->resolveEmployeeRm($employee);
        $empBRm = $this->formations->resolveEmployeeRm($occupant);

        if ($empARm !== $empBRm) {
            throw new MutasiRuleViolationException(
                "Tukar jabatan (Swap) hanya diperbolehkan untuk tingkat eselon/RM yang setara (Lateral Swap). Pegawai {$employee->nama} ({$empARm}) tidak setara dengan {$occupant->nama} ({$empBRm}). Gunakan alur mutasi ke formasi kosong."
            );
        }

        return $occupant;
    }

    private function mutasiResult(string $type, Employee $employee, MutasiRequest $request, ?string $displaced = null): array
    {
        return [
            'type' => $type,
            'employee' => $employee->nama,
            'to_jabatan' => $request->targetJabatan,
            'to_unit' => $request->targetUnitKerja,
            'displaced' => $displaced,
            'is_scheduled' => $request->isFuture(),
            'tmt_awal' => $request->tmtAwal,
        ];
    }

    /**
     * Atomically process an entire chain / batch of transfers in a single database transaction.
     * All movements share the same batch_id, collective SK metadata, and effective TMT.
     *
     * @throws MutasiRuleViolationException if validation fails
     */
    public function processChainMutasi(array $headerData, array $nodes): array
    {
        $nodes = $this->chains->prepareNodes($nodes);

        return DB::transaction(function () use ($headerData, $nodes) {
            // Serialize concurrent mutations into the same target units before re-validating occupancy
            Unit::whereIn('nama', array_unique(array_column($nodes, 'target_unit_kerja')))
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $validation = $this->chains->validateChainTopology($nodes);

            if (! $validation['is_valid']) {
                throw new MutasiRuleViolationException(
                    'Validasi rantai mutasi gagal: '.implode('; ', $validation['errors'])
                );
            }

            $batchId = Str::uuid()->toString();

            /** @var list<MutasiRequest> $requests */
            $requests = array_map(
                fn (array $node) => MutasiRequest::fromChainNode($headerData, $node),
                array_values($nodes)
            );

            // All participants share one SK, hence one TMT and one applied/staged state
            $isFutureTmt = $requests[0]->isFuture();

            // 1. Lock all participants and close their active definitive jobs when the TMT has arrived
            $lockedEmployees = [];
            foreach ($requests as $request) {
                $emp = Employee::with('currentJob')->where('nik', $request->employeeNik)->lockForUpdate()->firstOrFail();
                if (! $isFutureTmt && $request->isDefinitif() && $emp->currentJob) {
                    $emp->currentJob->update(['tmt_akhir' => $request->tmtAkhir()]);
                }
                $lockedEmployees[$request->employeeNik] = $emp;
            }

            // 2. Update employee records and insert new job histories
            $processed = [];

            foreach ($requests as $order => $request) {
                $emp = $lockedEmployees[$request->employeeNik];
                $profile = $this->resolvePlacementProfile($emp, $request);

                if (! $isFutureTmt && $request->isDefinitif()) {
                    $this->placement->apply(
                        $emp, $request->targetJabatan, $request->targetUnitKerja, $request->tmtAwal,
                        $profile + ['status_penugasan' => $request->statusJabatan]
                    );
                }

                $this->placement->recordHistory($emp, $request->targetJabatan, $request->targetUnitKerja, $request, $profile, [
                    'batch_id' => $batchId,
                    'chain_order' => $order + 1,
                ]);

                $processed[] = [
                    'employee_nik' => $emp->nik,
                    'nama' => $emp->nama,
                    'target_jabatan' => $request->targetJabatan,
                    'target_unit_kerja' => $request->targetUnitKerja,
                    'jenis_mutasi' => $request->jenisMutasi,
                ];
            }

            return [
                'type' => 'chain',
                'batch_id' => $batchId,
                'nomor_sk' => $requests[0]->nomorSk,
                'topology' => $validation['topology'],
                'topology_label' => $validation['topology_label'],
                'total_transfers' => count($processed),
                'items' => $processed,
                'unfilled_vacancies' => $validation['unfilled_vacancies'],
                'closed_vacancies' => $validation['closed_vacancies'],
                'is_scheduled' => $isFutureTmt,
                'tmt_awal' => $requests[0]->tmtAwal,
            ];
        });
    }

    /**
     * Profile attributes an employee takes over when placed into the target position:
     * RM band & Karpim/Karpel classification (DOMAIN.md §4.1) plus golongan / job grade.
     */
    private function resolvePlacementProfile(Employee $employee, MutasiRequest $request): array
    {
        $rmLevel = $this->formations->resolvePositionRm($request->targetJabatan);

        return [
            'rm_level' => $rmLevel,
            'level' => $this->formations->deriveLevelForRm($rmLevel, $employee->level),
            'golongan' => $request->newGolongan ?: $employee->golongan,
            'job_grade' => $request->newJobGrade ?: $employee->job_grade,
        ];
    }

    /** Move one employee to a (currently vacant) target position. */
    private function doTransfer(Employee $employee, MutasiRequest $request): void
    {
        $profile = $this->resolvePlacementProfile($employee, $request);

        if (! $request->isFuture() && $request->isDefinitif()) {
            // 1. Close current definitive job history
            $employee->currentJob?->update(['tmt_akhir' => $request->tmtAkhir()]);

            // 2. Update employee profile
            $this->placement->apply(
                $employee, $request->targetJabatan, $request->targetUnitKerja, $request->tmtAwal,
                $profile + [
                    'status_penugasan' => $request->statusJabatan,
                    'tanggal_berakhir_sanksi' => $request->tanggalBerakhirSanksi,
                ]
            );
        }

        // 3. Create new job history (applied immediately or staged for future TMT)
        $this->placement->recordHistory($employee, $request->targetJabatan, $request->targetUnitKerja, $request, $profile);
    }

    /**
     * Atomically swap two employees' positions (always Definitif — see resolveSwapOccupant).
     * Creates ZERO net vacancies — both old histories close and two new ones open simultaneously.
     * Both legs share one batch_id so scheduled activation & cancellation always act on the pair.
     */
    private function doSwap(Employee $empA, Employee $empB, MutasiRequest $request): void
    {
        // Snapshot both positions BEFORE touching anything
        $posA = $this->snapshotPosition($empA);
        $posB = $this->snapshotPosition($empB);

        $profileA = [
            'rm_level' => $posB['rm_level'] ?? $empA->rm_level,
            'level' => $posB['level'] ?? $empA->level,
            'golongan' => $request->newGolongan ?: $empA->golongan,
            'job_grade' => $request->newJobGrade ?: $empA->job_grade,
        ];
        $profileB = [
            'rm_level' => $posA['rm_level'] ?? $empB->rm_level,
            'level' => $posA['level'] ?? $empB->level,
            'golongan' => $posA['golongan'] ?? $empB->golongan,
            'job_grade' => $posA['job_grade'],
        ];

        if (! $request->isFuture()) {
            // 1. Close active job histories of both employees
            $empA->currentJob?->update(['tmt_akhir' => $request->tmtAkhir()]);
            $empB->currentJob?->update(['tmt_akhir' => $request->tmtAkhir()]);

            // 2. Swap employee profiles (employee B keeps own golongan / job grade)
            $this->placement->apply(
                $empA, $posB['jabatan'], $posB['unit_kerja'], $request->tmtAwal,
                $profileA + ['tanggal_berakhir_sanksi' => $request->tanggalBerakhirSanksi]
            );
            $this->placement->apply(
                $empB, $posA['jabatan'], $posA['unit_kerja'], $request->tmtAwal,
                Arr::only($profileB, ['rm_level', 'level'])
            );
        }

        // 3. Create two new job history rows (applied immediately or staged for future TMT)
        $baseNote = $request->catatan ?? 'Tukar jabatan / rotasi bersilang';
        $batchId = Str::uuid()->toString();

        $this->placement->recordHistory($empA, $posB['jabatan'], $posB['unit_kerja'], $request, $profileA, [
            'catatan' => "{$baseNote} — bertukar dengan {$empB->nama}",
            'batch_id' => $batchId,
            'chain_order' => 1,
        ]);
        $this->placement->recordHistory($empB, $posA['jabatan'], $posA['unit_kerja'], $request, $profileB, [
            'catatan' => "{$baseNote} — bertukar dengan {$empA->nama}",
            'is_dispensasi' => false,
            'alasan_dispensasi' => null,
            'tanggal_berakhir_sanksi' => null,
            'batch_id' => $batchId,
            'chain_order' => 2,
        ]);
    }

    private function snapshotPosition(Employee $employee): array
    {
        return [
            'jabatan' => $employee->jabatan,
            'unit_kerja' => $employee->unit_kerja,
            'level' => $employee->level,
            'rm_level' => $employee->rm_level,
            'golongan' => $employee->golongan,
            'job_grade' => $employee->job_grade,
        ];
    }

    // -------------------------------------------------------------------------
    // Public API (DOMAIN.md §9.2) — delegates to the focused collaborators
    // -------------------------------------------------------------------------

    /** @see FormationService::analyzeTarget() */
    public function analyzeTarget(string $jabatan, string $unitKerja): array
    {
        return $this->formations->analyzeTarget($jabatan, $unitKerja);
    }

    /** @see FormationService::getAvailableTargetPositions() */
    public function getAvailableTargetPositions(Employee $emp, string $targetUnitKerja, string $jenisMutasi): array
    {
        return $this->formations->getAvailableTargetPositions($emp, $targetUnitKerja, $jenisMutasi);
    }

    /** @see FormationService::resolvePositionRm() */
    public function resolvePositionRm(string $jabatan): string
    {
        return $this->formations->resolvePositionRm($jabatan);
    }

    /** @see FormationService::deriveLevelForRm() */
    public function deriveLevelForRm(string $rmLevel, ?string $fallback = null): ?string
    {
        return $this->formations->deriveLevelForRm($rmLevel, $fallback);
    }

    /** @see FormationService::resolveEmployeeRm() */
    public function resolveEmployeeRm(Employee $employee): string
    {
        return $this->formations->resolveEmployeeRm($employee);
    }

    /** @see FormationService::getPositionFlags() */
    public function getPositionFlags(string $jabatan): array
    {
        return $this->formations->getPositionFlags($jabatan);
    }

    /** @see MutasiGovernanceGuard::checkTenureCompliance() */
    public function checkTenureCompliance(Employee $emp, string $targetUnitKerja): array
    {
        return $this->guard->checkTenureCompliance($emp, $targetUnitKerja);
    }

    /** @see MutasiGovernanceGuard::checkPromotionEligibility() */
    public function checkPromotionEligibility(Employee $emp, string $targetJabatan): array
    {
        return $this->guard->checkPromotionEligibility($emp, $targetJabatan);
    }

    /** @see ChainTopologyAnalyzer::validateChainTopology() */
    public function validateChainTopology(array $nodes): array
    {
        return $this->chains->validateChainTopology($nodes);
    }

    /** @see ChainTopologyAnalyzer::prepareNodes() */
    public function prepareChainNodes(array $nodes): array
    {
        return $this->chains->prepareNodes($nodes);
    }

    /** @see ChainTopologyAnalyzer::orderNodesByFlow() */
    public function orderChainNodesByFlow(array $nodes): array
    {
        return $this->chains->orderNodesByFlow($nodes);
    }

    /** @see PlacementWriter::apply() */
    public function applyPlacementToEmployee(Employee $employee, string $jabatan, string $unitKerja, string $tmt, array $attributes = []): void
    {
        $this->placement->apply($employee, $jabatan, $unitKerja, $tmt, $attributes);
    }

    /** @see ScheduledMutationService::applyEffectiveMutations() */
    public function applyEffectiveMutations(?string $targetDate = null): array
    {
        return $this->scheduled->applyEffectiveMutations($targetDate);
    }

    /** @see ScheduledMutationService::applyScheduledMutation() */
    public function applyScheduledMutation(int $jobHistoryId): array
    {
        return $this->scheduled->applyScheduledMutation($jobHistoryId);
    }

    /** @see ScheduledMutationService::cancelScheduledMutation() */
    public function cancelScheduledMutation(int|string $identifier, ?string $reason = null, ?int $cancelledBy = null): array
    {
        return $this->scheduled->cancelScheduledMutation($identifier, $reason, $cancelledBy);
    }

    /** @see MutasiHistoryReader::getMutasiBatch() */
    public function getMutasiBatch(string $batchId): Collection
    {
        return $this->history->getMutasiBatch($batchId);
    }

    /** @see MutasiHistoryReader::getMutasiHistoryQuery() */
    public function getMutasiHistoryQuery(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $jenisMutasi = null,
        ?bool $isApplied = null,
        bool $onlyDue = false,
        string $sortBy = 'tmt_awal',
        string $sortDirection = 'desc',
        ?string $sumber = null,
    ): Builder {
        return $this->history->getMutasiHistoryQuery($search, $unitKerja, $jenisMutasi, $isApplied, $onlyDue, $sortBy, $sortDirection, $sumber);
    }

    /** @see MutasiHistoryReader::getMutasiHistory() */
    public function getMutasiHistory(
        ?string $search = null,
        ?string $unitKerja = null,
        ?string $jenisMutasi = null,
        ?bool $isApplied = null,
        bool $onlyDue = false,
        int $perPage = 15,
        string $sortBy = 'tmt_awal',
        string $sortDirection = 'desc',
        ?string $sumber = null,
    ): LengthAwarePaginator {
        return $this->history->getMutasiHistory($search, $unitKerja, $jenisMutasi, $isApplied, $onlyDue, $perPage, $sortBy, $sortDirection, $sumber);
    }

    /** @see MutasiHistoryReader::getMutasiHistoryKpis() */
    public function getMutasiHistoryKpis(?string $sumber = null): array
    {
        return $this->history->getMutasiHistoryKpis($sumber);
    }

    /** @see MutasiHistoryReader::getScheduledMutationsCount() */
    public function getScheduledMutationsCount(): int
    {
        return $this->history->getScheduledMutationsCount();
    }

    /** @see MutasiHistoryReader::getMutasiDetail() */
    public function getMutasiDetail(int $id): JobHistory
    {
        return $this->history->getMutasiDetail($id);
    }

    /** @see TalentProfileBuilder::build() */
    public function getTalentProfile(string $nik): ?array
    {
        return $this->profiles->build($nik);
    }

    /** @see MutasiLookup::getSelectableEmployees() */
    public function getSelectableEmployees(?string $unitKerja = null): Collection
    {
        return $this->lookup->getSelectableEmployees($unitKerja);
    }

    /** @see MutasiLookup::getGroupedUnits() */
    public function getGroupedUnits(): array
    {
        return $this->lookup->getGroupedUnits();
    }

    /** @see MutasiLookup::findEmployee() */
    public function findEmployee(string $nik): ?Employee
    {
        return $this->lookup->findEmployee($nik);
    }

    /** @see MutasiLookup::listTargetPositions() */
    public function listTargetPositions(string $nik, string $targetUnitKerja, string $jenisMutasi): array
    {
        return $this->lookup->listTargetPositions($nik, $targetUnitKerja, $jenisMutasi);
    }

    /** @see MutasiLookup::tenureComplianceFor() */
    public function tenureComplianceFor(string $nik, string $targetUnitKerja): ?array
    {
        return $this->lookup->tenureComplianceFor($nik, $targetUnitKerja);
    }

    /** @see MutasiLookup::promotionEligibilityFor() */
    public function promotionEligibilityFor(string $nik, string $targetJabatan): ?array
    {
        return $this->lookup->promotionEligibilityFor($nik, $targetJabatan);
    }

    /** @see MutasiLookup::getEmployeeSnapshot() */
    public function getEmployeeSnapshot(string $nik): ?array
    {
        return $this->lookup->getEmployeeSnapshot($nik);
    }

    /** @see MutasiLookup::getKnownJabatan() */
    public function getKnownJabatan(): Collection
    {
        return $this->lookup->getKnownJabatan();
    }

    /** @see MutasiLookup::getKnownUnits() */
    public function getKnownUnits(): Collection
    {
        return $this->lookup->getKnownUnits();
    }
}
