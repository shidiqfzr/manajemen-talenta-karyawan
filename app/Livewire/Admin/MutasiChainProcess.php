<?php

namespace App\Livewire\Admin;

use App\Exceptions\MutasiIntegrityException;
use App\Exceptions\MutasiRuleViolationException;
use App\Models\MutasiChainProposal;
use App\Services\Mutasi\ChainProposalService;
use App\Services\Mutasi\DispensasiBasis;
use App\Services\Mutasi\MutasiPolicy;
use App\Services\MutasiService;
use App\Support\HrisPermission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

class MutasiChainProcess extends Component
{
    use WithFileUploads;

    /** Properties that hold the proposal's inputs: refused while the proposal is locked. */
    private const INPUT_PROPERTIES = ['nomorSk', 'tanggalSk', 'tmtAwal', 'catatanHeader', 'fileSk', 'nodes'];

    // ── Usulan (saved draft / approval) ─────────────────────────────────────
    #[Locked]
    public ?int $proposalId = null;

    public bool $showRejectModal = false;

    public string $rejectReason = '';

    public string $notice = '';

    // ── Header SK Kolektif ──────────────────────────────────────────────────
    public string $nomorSk = '';

    public string $tanggalSk = '';

    public string $tmtAwal = '';

    public string $catatanHeader = '';

    public $fileSk = null;

    // ── Mata Rantai Mutasi (Nodes) ──────────────────────────────────────────
    public array $nodes = [];

    // ── Topology & Validation State ─────────────────────────────────────────
    public ?array $chainAnalysis = null;

    public bool $showReviewModal = false;

    // ── Talent Intelligence Slide-Over Drawer ───────────────────────────────
    public ?string $selectedTalentNik = null;

    public ?array $selectedTalentData = null;

    public bool $showTalentDrawer = false;

    // ── Master Data Cache (In-Memory for Component Lifecycle) ───────────────
    public array $unitsList = [];

    /**
     * Changes placements and shows personal data: check the permission on every request, because route
     * middleware is not re-applied to Livewire update requests. Preparers build proposals; approvers may
     * open them to decide (SECURITY.md §3, config('hris.permissions')).
     */
    public function boot(): void
    {
        abort_unless(
            Gate::any([HrisPermission::MUTASI_PREPARE, HrisPermission::MUTASI_APPROVE]),
            403,
            'Unauthorized.'
        );
    }

    public function mount(MutasiService $service, ?int $usulan = null): void
    {
        $this->unitsList = $service->getKnownUnits()->toArray();

        if ($usulan) {
            $proposal = MutasiChainProposal::find($usulan);
            abort_unless($proposal && $this->canView($proposal), 404);
            $this->proposalId = $proposal->id;
            $this->loadProposalState($proposal);

            return;
        }

        abort_unless(Gate::allows(HrisPermission::MUTASI_PREPARE), 403, 'Unauthorized.');

        $this->tanggalSk = Carbon::today()->toDateString();
        // Default TMT: first day of next month
        $this->tmtAwal = Carbon::now()->addMonth()->startOfMonth()->toDateString();

        // Initialize with 2 empty node rows
        $this->addNode();
        $this->addNode();
    }

    // -------------------------------------------------------------------------
    // Usulan: access, locking and state restore
    // -------------------------------------------------------------------------

    /** The loaded proposal, fresh from the database (never from client state). */
    #[Computed]
    public function proposal(): ?MutasiChainProposal
    {
        return $this->proposalId ? MutasiChainProposal::with('events')->find($this->proposalId) : null;
    }

    /** Its maker sees a proposal; so does every approver, once it has been submitted. */
    private function canView(MutasiChainProposal $proposal): bool
    {
        $user = auth()->user();

        return (int) $proposal->created_by === (int) $user->id
            || ($proposal->submitted_at !== null && $user->can(HrisPermission::MUTASI_APPROVE));
    }

    /**
     * Inputs are locked when the user may not prepare, or when a loaded proposal is not an editable draft
     * of this user (pending, executed, expired, or someone else's).
     */
    #[Computed]
    public function isLocked(): bool
    {
        if (! Gate::allows(HrisPermission::MUTASI_PREPARE)) {
            return true;
        }

        $proposal = $this->proposal;

        return $proposal !== null
            && (! $proposal->isEditable() || (int) $proposal->created_by !== (int) auth()->id());
    }

    /**
     * What the primary action does: 'approve' (checker decides a pending proposal), 'submit' (four-eyes
     * policy on: the maker submits), 'execute' (policy off: issue the SK directly), or 'none'.
     */
    #[Computed]
    public function reviewMode(): string
    {
        $proposal = $this->proposal;
        if ($proposal?->isPending()) {
            return ChainProposalService::canDecide($proposal, auth()->user()) ? 'approve' : 'none';
        }
        if ($this->isLocked) {
            return 'none';
        }
        if (ChainProposalService::requiresApproval()) {
            return 'submit';
        }

        return Gate::allows(HrisPermission::MUTASI_APPROVE) ? 'execute' : 'none';
    }

    /** Refuse any change to the proposal inputs while locked (the inputs are also disabled in the view). */
    public function updating(string $property): void
    {
        $root = explode('.', $property)[0];
        if (in_array($root, self::INPUT_PROPERTIES, true)) {
            $this->assertUnlocked();
        }
    }

    private function assertUnlocked(): void
    {
        unset($this->isLocked, $this->proposal);
        abort_if($this->isLocked, 403, 'Usulan ini terkunci dan tidak dapat diubah.');
    }

    /** Restore SK header and participants from stored inputs; every analysis is recomputed on current data. */
    private function loadProposalState(MutasiChainProposal $proposal): void
    {
        $header = ChainProposalService::headerOf($proposal);
        $this->nomorSk = $header['nomor_sk'];
        $this->tanggalSk = $header['tanggal_sk'];
        $this->tmtAwal = $header['tmt_awal'];
        $this->catatanHeader = $header['catatan'];
        $this->fileSk = null;

        $this->nodes = [];
        foreach ((array) $proposal->payload as $input) {
            $node = $this->createEmptyNode(
                $input['employee_nik'] ?: null,
                null,
                null,
                $input['jenis_mutasi'] ?: 'MUTASI_UNIT'
            );
            // Stored inputs win over snapshot defaults, so the approved fingerprint matches exactly
            foreach (ChainProposalService::INPUT_FIELDS as $field) {
                $node[$field] = $input[$field] ?? '';
            }
            $node['tmt_akhir_estimasi'] = $node['tmt_akhir_estimasi'] ?: null;
            $this->nodes[] = $node;
        }

        while (count($this->nodes) < MutasiPolicy::MIN_CHAIN_PARTICIPANTS) {
            $this->nodes[] = $this->createEmptyNode();
        }

        foreach (array_keys($this->nodes) as $idx) {
            $this->evaluateNode($idx);
            $this->syncDispensasi($idx);
        }
        $this->runTopologyAnalysis();
    }

    /**
     * Add a new node slot to the transfer chain.
     */
    public function addNode(
        ?string $employeeNik = null,
        ?string $targetJabatan = null,
        ?string $targetUnit = null,
        string $jenisMutasi = 'MUTASI_UNIT'
    ): void {
        $this->assertUnlocked();
        $newNode = $this->createEmptyNode($employeeNik, $targetJabatan, $targetUnit, $jenisMutasi);
        $this->nodes[] = $newNode;
        $this->evaluateNode(count($this->nodes) - 1);
        $this->runTopologyAnalysis();
    }

    /**
     * Create default data structure for a chain node.
     */
    private function createEmptyNode(
        ?string $employeeNik = null,
        ?string $targetJabatan = null,
        ?string $targetUnit = null,
        string $jenisMutasi = 'MUTASI_UNIT'
    ): array {
        $node = [
            'employee_nik' => $employeeNik ?? '',
            'employee_nama' => '',
            'current_jabatan' => '',
            'current_unit' => '',
            'current_rm' => '',
            'current_golongan' => '',
            'target_unit_kerja' => $targetUnit ?? '',
            'target_jabatan' => $targetJabatan ?? '',
            'jenis_mutasi' => $jenisMutasi,
            'status_jabatan' => 'Definitif',
            'tmt_akhir_estimasi' => null,
            'is_dispensasi' => false,
            'alasan_dispensasi' => '',
            'dispensasi_direksi' => false,
            'alasan_dispensasi_direksi' => '',
            'tanggal_dispensasi_direksi' => '',
            'dispensasi_tenure' => false,
            'alasan_dispensasi_tenure' => '',
            'dispensasi_promosi' => false,
            'alasan_dispensasi_promosi' => '',
            'new_golongan' => '',
            'new_job_grade' => '',
            'catatan' => '',
            // Dynamic inspection
            'occupant_nik' => null,
            'occupant_nama' => null,
            'link_type' => null,
            'tenure_compliant' => true,
            'tenure_msg' => '',
            'promotion_eligible' => true,
            'promotion_msg' => '',
            'tenure_analysis' => null,
            'promotion_analysis' => null,
            'profile_data' => null,
        ];

        if ($employeeNik) {
            $snapshot = $this->service()->getEmployeeSnapshot($employeeNik);
            if ($snapshot) {
                $this->assignEmployeeAttributes($node, $snapshot);
            }
        }

        return $node;
    }

    /**
     * Map an employee snapshot (MutasiService::getEmployeeSnapshot) to a node array (DRY Principle).
     */
    private function assignEmployeeAttributes(array &$node, array $snapshot): void
    {
        $node['employee_nik'] = $snapshot['nik'];
        $node['employee_nama'] = $snapshot['nama'];
        $node['current_jabatan'] = $snapshot['jabatan'];
        $node['current_unit'] = $snapshot['unit_kerja'];
        $node['current_rm'] = $snapshot['rm_level'];
        $node['current_golongan'] = $snapshot['golongan'];
        $node['new_golongan'] = $snapshot['golongan'];
        $node['new_job_grade'] = (string) $snapshot['job_grade'];
        $node['profile_data'] = $snapshot['profile'];
    }

    private function service(): MutasiService
    {
        return app(MutasiService::class);
    }

    /**
     * Clear employee attributes from a node array (DRY Principle).
     */
    private function resetEmployeeAttributes(array &$node): void
    {
        $node['employee_nik'] = '';
        $node['employee_nama'] = '';
        $node['current_jabatan'] = '';
        $node['current_unit'] = '';
        $node['current_rm'] = '';
        $node['current_golongan'] = '';
        $node['new_golongan'] = '';
        $node['new_job_grade'] = '';
        $node['profile_data'] = null;
        $node['tenure_analysis'] = null;
        $node['promotion_analysis'] = null;
        $node['is_dispensasi'] = false;
        $node['alasan_dispensasi'] = '';
        $node['dispensasi_direksi'] = false;
        $node['alasan_dispensasi_direksi'] = '';
        $node['tanggal_dispensasi_direksi'] = '';
        $node['dispensasi_tenure'] = false;
        $node['alasan_dispensasi_tenure'] = '';
        $node['dispensasi_promosi'] = false;
        $node['alasan_dispensasi_promosi'] = '';
    }

    /**
     * Populate employee information into an existing node slot.
     */
    private function populateEmployeeIntoNode(int $index, ?string $nik): void
    {
        if (! isset($this->nodes[$index])) {
            return;
        }

        $snapshot = $nik ? $this->service()->getEmployeeSnapshot($nik) : null;
        if ($snapshot) {
            $this->assignEmployeeAttributes($this->nodes[$index], $snapshot);
            if ($this->nodes[$index]['jenis_mutasi'] === 'ROTASI') {
                $this->nodes[$index]['target_unit_kerja'] = $snapshot['unit_kerja'];
            }
        } else {
            $this->resetEmployeeAttributes($this->nodes[$index]);
        }

        $this->evaluateNode($index);
    }

    /**
     * Remove a node from the chain.
     */
    public function removeNode(int $index): void
    {
        $this->assertUnlocked();
        if (count($this->nodes) <= MutasiPolicy::MIN_CHAIN_PARTICIPANTS) {
            $this->addError('nodes', 'Rantai mutasi kolektif memerlukan minimal '.MutasiPolicy::MIN_CHAIN_PARTICIPANTS.' karyawan.');

            return;
        }

        unset($this->nodes[$index]);
        $this->nodes = array_values($this->nodes);
        $this->runTopologyAnalysis();
    }

    /**
     * One-click action: When Node A targets the position occupied by Employee B,
     * immediately connect Employee B sequentially as the next link in the chain!
     *
     * Best Practice Enterprise Logic:
     * 1. If immediate next slot (index + 1) exists and is empty, populate it directly.
     * 2. Else if any later slot is empty, re-use it and insert at (index + 1).
     * 3. Else insert a new slot at (index + 1) via array_splice to preserve causal domino order.
     */
    public function connectOccupantToChain(int $nodeIndex): void
    {
        $this->assertUnlocked();
        if (! isset($this->nodes[$nodeIndex])) {
            return;
        }

        $occupantNik = $this->nodes[$nodeIndex]['occupant_nik'] ?? null;
        if (! $occupantNik) {
            return;
        }

        // Check if occupant is already in nodes
        foreach ($this->nodes as $node) {
            if ($node['employee_nik'] === $occupantNik) {
                return; // already connected
            }
        }

        $nextIndex = $nodeIndex + 1;

        // Priority 1: If next slot exists and has no employee selected, populate it directly
        if (isset($this->nodes[$nextIndex]) && empty($this->nodes[$nextIndex]['employee_nik'])) {
            $this->populateEmployeeIntoNode($nextIndex, $occupantNik);
        } else {
            // Priority 2: Check if there's an unused empty slot elsewhere in the chain
            $emptyIndex = null;
            foreach ($this->nodes as $i => $n) {
                if ($i > $nodeIndex && empty($n['employee_nik'])) {
                    $emptyIndex = $i;
                    break;
                }
            }

            if ($emptyIndex !== null) {
                // Re-use empty slot by removing it and inserting at $nextIndex
                array_splice($this->nodes, $emptyIndex, 1);
            }

            $newNode = $this->createEmptyNode($occupantNik);
            array_splice($this->nodes, $nextIndex, 0, [$newNode]);
        }

        $this->nodes = array_values($this->nodes);

        // Re-evaluate all nodes to update linkage and domino indicators
        foreach (array_keys($this->nodes) as $idx) {
            $this->evaluateNode($idx);
        }

        $this->runTopologyAnalysis();
    }

    /**
     * Lifecycle hook when nodes array is updated.
     */
    public function updatedNodes($value, $key): void
    {
        $parts = explode('.', (string) $key);
        if ($parts[0] === 'nodes') {
            array_shift($parts);
        }
        $index = (int) ($parts[0] ?? 0);
        $field = $parts[1] ?? null;

        if ($field === 'employee_nik') {
            $this->populateEmployeeIntoNode($index, $value ? (string) $value : null);
        } elseif ($field === 'jenis_mutasi') {
            if ($this->nodes[$index]['jenis_mutasi'] === 'ROTASI') {
                if (! empty($this->nodes[$index]['current_unit'])) {
                    $this->nodes[$index]['target_unit_kerja'] = $this->nodes[$index]['current_unit'];
                }
            } elseif ($this->nodes[$index]['target_unit_kerja'] === $this->nodes[$index]['current_unit']) {
                $this->nodes[$index]['target_unit_kerja'] = '';
            }
            $this->nodes[$index]['target_jabatan'] = '';
        } elseif ($field === 'target_unit_kerja') {
            $this->nodes[$index]['target_jabatan'] = '';
        } elseif ($field === 'is_dispensasi') {
            if (! empty($value)) {
                if (! ($this->nodes[$index]['tenure_compliant'] ?? true)) {
                    $this->nodes[$index]['dispensasi_tenure'] = true;
                }
            } else {
                $this->nodes[$index]['dispensasi_tenure'] = false;
            }
        } elseif ($field === 'dispensasi_tenure') {
            $this->nodes[$index]['is_dispensasi'] = ! empty($this->nodes[$index]['dispensasi_tenure']);
        }

        $this->evaluateNode($index);
        if (in_array($field, ['alasan_dispensasi_direksi', 'tanggal_dispensasi_direksi', 'target_jabatan', 'target_unit_kerja', 'jenis_mutasi'], true)) {
            $this->syncDispensasi($index);
        }
        $this->runTopologyAnalysis();
    }

    /**
     * Participants whose regulation exception still lacks Direksi evidence (DESIGN.md §7.2: summarise before the detail).
     * Shown beside the primary action, same as Mutasi Tunggal.
     *
     * @return list<string>
     */
    #[Computed]
    public function outstandingIssues(): array
    {
        $issues = [];

        foreach ($this->nodes as $node) {
            if (empty($node['employee_nik']) || empty($node['target_jabatan']) || empty($node['target_unit_kerja'])) {
                continue;
            }

            $tenureUnmet = ! empty($node['tenure_analysis'])
                && ! ($node['tenure_analysis']['is_compliant'] ?? true)
                && ($node['tenure_analysis']['is_applicable'] ?? true);
            $promoUnmet = ! empty($node['promotion_analysis'])
                && ($node['promotion_analysis']['is_applicable'] ?? false)
                && ! ($node['promotion_analysis']['is_eligible'] ?? true);

            if (($tenureUnmet || $promoUnmet) && empty($node['dispensasi_direksi'])) {
                $issues[] = ($node['employee_nama'] ?: 'Karyawan').': dasar persetujuan Direksi belum dilengkapi.';
            }
        }

        return $issues;
    }

    /**
     * One Direksi dispensation per node covers every rule exception of that node (masa dinas unit, kualifikasi promosi).
     * It is evidenced by a document reference + approval date (DispensasiBasis) and fanned out to the per-rule fields
     * that the service and audit trail use, only for rules that actually apply.
     */
    private function syncDispensasi(int $index): void
    {
        if (! isset($this->nodes[$index])) {
            return;
        }

        $node = $this->nodes[$index];
        $reference = (string) ($node['alasan_dispensasi_direksi'] ?? '');
        $date = (string) ($node['tanggal_dispensasi_direksi'] ?? '');
        $approved = DispensasiBasis::isComplete($reference, $date);
        $basis = $approved ? DispensasiBasis::compose($reference, $date) : '';
        $node['dispensasi_direksi'] = $approved;

        $needsTenure = ! empty($node['tenure_analysis'])
            && ! ($node['tenure_analysis']['is_compliant'] ?? true)
            && ($node['tenure_analysis']['is_applicable'] ?? true);
        $needsPromo = ! empty($node['promotion_analysis'])
            && ($node['promotion_analysis']['is_applicable'] ?? false)
            && ! ($node['promotion_analysis']['is_eligible'] ?? true);

        $node['dispensasi_tenure'] = $approved && $needsTenure;
        $node['alasan_dispensasi_tenure'] = $node['dispensasi_tenure'] ? $basis : '';
        $node['is_dispensasi'] = $node['dispensasi_tenure'];
        $node['dispensasi_promosi'] = $approved && $needsPromo;
        $node['alasan_dispensasi_promosi'] = $node['dispensasi_promosi'] ? $basis : '';

        $this->nodes[$index] = $node;
    }

    /**
     * Inspect single node compliance and target occupant.
     */
    private function evaluateNode(int $index): void
    {
        if (! isset($this->nodes[$index])) {
            return;
        }

        $node = $this->nodes[$index];
        $nik = $node['employee_nik'];
        $targetJabatan = $node['target_jabatan'];
        $targetUnit = $node['target_unit_kerja'];

        if (! $nik || ! $targetJabatan || ! $targetUnit) {
            $node['occupant_nik'] = null;
            $node['occupant_nama'] = null;
            $node['link_type'] = null;
            $node['tenure_compliant'] = true;
            $node['tenure_msg'] = '';
            $node['promotion_eligible'] = true;
            $node['promotion_msg'] = '';
            $node['tenure_analysis'] = null;
            $node['promotion_analysis'] = null;

            $this->nodes[$index] = $node;

            return;
        }

        $service = $this->service();

        // 1. Analyze occupant
        // Bookings of the chain's own participants do not count against the target's seats.
        $participantNiks = array_values(array_filter(array_column($this->nodes, 'employee_nik')));
        $targetAnalysis = $service->analyzeTarget($targetJabatan, $targetUnit, $participantNiks);
        if ($targetAnalysis['type'] === 'pool') {
            $node['occupant_nik'] = null;
            $node['occupant_nama'] = null;
            $node['link_type'] = 'pool';
        } elseif ($targetAnalysis['vacant']) {
            $node['occupant_nik'] = null;
            $node['occupant_nama'] = null;
            $node['link_type'] = 'vacant';
        } elseif (($targetAnalysis['occupant'] ?? null) === null) {
            // Reserved by a scheduled mutation (or without any seat): nobody to connect to, resolve that first.
            $node['occupant_nik'] = null;
            $node['occupant_nama'] = null;
            $node['link_type'] = 'conflict';
        } else {
            $occupant = $targetAnalysis['occupant'];
            $node['occupant_nik'] = $occupant->nik;
            $node['occupant_nama'] = $occupant->nama;

            // Check if occupant is also in the chain
            $allNiks = array_column($this->nodes, 'employee_nik');
            $node['link_type'] = in_array($occupant->nik, $allNiks, true) ? 'chain' : 'conflict';
        }

        // 2. Compliance checks
        $tenure = $service->tenureComplianceFor($nik, $targetUnit);
        if ($tenure) {
            $node['tenure_analysis'] = $tenure;
            $node['tenure_compliant'] = $tenure['is_compliant'];
            $node['tenure_msg'] = $tenure['message'];
        }

        $promo = $service->promotionEligibilityFor($nik, $targetJabatan);
        if ($promo) {
            $node['promotion_analysis'] = $promo;
            $node['promotion_eligible'] = $promo['is_eligible'];
            $node['promotion_msg'] = $promo['summary'];
        }

        $this->nodes[$index] = $node;
    }

    /**
     * Run full topology analysis on the active chain.
     */
    public function runTopologyAnalysis(): void
    {
        // Refresh occupant chain link status for all nodes
        $allNiks = array_filter(array_column($this->nodes, 'employee_nik'));
        foreach ($this->nodes as $idx => $node) {
            if (! empty($node['occupant_nik'])) {
                $this->nodes[$idx]['link_type'] = in_array($node['occupant_nik'], $allNiks, true) ? 'chain' : 'conflict';
            }
        }

        // Only validate if enough nodes have employee and target filled
        $service = $this->service();
        $validNodes = $service->prepareChainNodes($this->nodes);

        if (count($validNodes) < MutasiPolicy::MIN_CHAIN_PARTICIPANTS) {
            $this->chainAnalysis = [
                'is_valid' => false,
                'topology' => 'incomplete',
                'topology_label' => 'Tahap Penyusunan (Belum Lengkap)',
                'errors' => ['Lengkapi minimal '.MutasiPolicy::MIN_CHAIN_PARTICIPANTS.' karyawan beserta formasi dan unit kerja target.'],
                'warnings' => [],
                'closed_vacancies' => [],
                'unfilled_vacancies' => [],
            ];

            return;
        }

        $this->chainAnalysis = $service->validateChainTopology($validNodes);
    }

    /**
     * Nodes ordered by causal domino flow for the topology flowchart (graph logic lives in the service).
     */
    public function getOrderedFlowNodes(): array
    {
        return $this->service()->orderChainNodesByFlow($this->nodes);
    }

    /**
     * Compute multi-cluster flow structures (Weakly Connected Components) for the executive visualizer.
     * Each cluster represents an independent rotation cycle or domino pipeline within the shared collective SK.
     */
    public function getOrderedFlowClusters(): array
    {
        if (empty($this->nodes)) {
            return [];
        }

        // Map live node attributes (like NIK, names, form status) by index
        $indexedNodes = [];
        foreach ($this->nodes as $idx => $node) {
            $indexedNodes[$idx] = array_merge($node, [
                'original_slot_index' => $idx,
                'slot_number' => $idx + 1,
            ]);
        }

        // If chain analysis with decomposed clusters is available, use it!
        if (! empty($this->chainAnalysis['clusters'])) {
            $clusters = $this->chainAnalysis['clusters'];
            foreach ($clusters as &$cluster) {
                $enhancedNodes = [];
                foreach ($cluster['node_indices'] as $idx) {
                    if (isset($indexedNodes[$idx])) {
                        $enhancedNodes[] = $indexedNodes[$idx];
                    }
                }
                $cluster['nodes'] = $enhancedNodes;
                // Shared anchor of the diagram card and the slot group below it
                $cluster['key'] = 'klaster-'.$cluster['id'];
            }
            unset($cluster);

            return $clusters;
        }

        // Draft phase before full validation: split into real linked flows, never one fake chain
        $segments = $this->getDraftFlowSegments();
        $clusters = [];
        $number = 1;

        foreach ($segments['linked'] as $segment) {
            $clusters[] = $this->draftCluster(
                $number,
                "Alur Mutasi #{$number} (Tahap Penyusunan)",
                $segment
            );
            $number++;
        }

        if (! empty($segments['loose'])) {
            $clusters[] = $this->draftCluster(
                $number,
                empty($clusters) ? 'Alur Mutasi (Tahap Penyusunan)' : 'Belum Masuk Alur Mutasi',
                $segments['loose'],
                loose: ! empty($clusters)
            );
        }

        return $clusters;
    }

    /**
     * Split draft nodes (no valid topology yet) into flows that are really linked:
     * a node continues the previous one only if it targets the seat of the node that follows it.
     * Nodes without any link are collected as "loose" so no arrow is ever implied between them.
     *
     * @return array{linked: array<int, array<int, array>>, loose: array<int, array>}
     */
    private function getDraftFlowSegments(): array
    {
        $segments = [];
        $current = [];

        foreach ($this->getOrderedFlowNodes() as $node) {
            $previous = $current ? end($current) : null;
            $continues = $previous
                && ! empty($previous['occupant_nik'])
                && $previous['occupant_nik'] === ($node['employee_nik'] ?? null);

            if (! $continues && $current) {
                $segments[] = $current;
                $current = [];
            }
            $current[] = $node;
        }
        if ($current) {
            $segments[] = $current;
        }

        $linked = [];
        $loose = [];
        foreach ($segments as $segment) {
            if (count($segment) >= 2) {
                $linked[] = $segment;
            } else {
                array_push($loose, ...$segment);
            }
        }

        usort($linked, fn ($a, $b) => min(array_column($a, 'original_slot_index')) <=> min(array_column($b, 'original_slot_index')));
        usort($loose, fn ($a, $b) => $a['original_slot_index'] <=> $b['original_slot_index']);

        return ['linked' => $linked, 'loose' => $loose];
    }

    /**
     * @param  array<int, array>  $nodes
     */
    /**
     * $loose marks nodes left outside the real flows next to other flows (needs attention, warning tone);
     * a sole drafting group is a normal starting state and stays neutral.
     *
     * @param  array<int, array>  $nodes
     */
    private function draftCluster(int $id, string $title, array $nodes, bool $loose = false): array
    {
        return [
            'id' => $id,
            'key' => 'draft-'.$id,
            'type' => 'draft',
            'is_loose' => $loose,
            'title' => $title,
            'type_label' => 'Tahap Penyusunan',
            'is_zero_vacancy' => false,
            'node_indices' => array_column($nodes, 'original_slot_index'),
            'nodes' => $nodes,
            'loopback' => null,
            'closed_vacancies' => [],
            'unfilled_vacancies' => [],
        ];
    }

    /**
     * Group the editable slot cards by the flow (alur mutasi) they belong to (Rincian Mutasi Karyawan per alur).
     * Membership comes from the topology analyzer; this method never recomputes domain logic.
     * Slots that are not part of any analysed flow (new, incomplete or stale) fall into one
     * "Belum terhubung" group, so every slot stays editable and global slot numbering (#1..#N) is kept.
     * Each group carries its status (vacancies left behind, conflicts) and the anchor it shares with
     * its diagram card, so admins can move between the overview and the detail of one flow.
     *
     * @return array<int, array{key: string, title: string, type: string, is_zero_vacancy: bool, is_loose: bool, indices: array<int, int>, cluster_id: ?int, unfilled_vacancies: array<int, array>, conflict_count: int, in_diagram: bool}>
     */
    public function getSlotGroups(): array
    {
        $allIndices = array_keys($this->nodes);
        if (empty($allIndices)) {
            return [];
        }

        $groups = [];
        $assigned = [];

        foreach ($this->getOrderedFlowClusters() as $cluster) {
            $indices = array_values(array_filter(
                $cluster['node_indices'] ?? [],
                fn ($idx) => isset($this->nodes[$idx]) && ! isset($assigned[$idx])
            ));
            if (empty($indices)) {
                continue;
            }
            foreach ($indices as $idx) {
                $assigned[$idx] = true;
            }
            $groups[] = [
                'key' => $cluster['key'],
                'title' => $cluster['title'],
                'type' => $cluster['type'],
                'is_zero_vacancy' => (bool) ($cluster['is_zero_vacancy'] ?? false),
                'is_loose' => (bool) ($cluster['is_loose'] ?? false),
                'indices' => $indices,
                'cluster_id' => $cluster['type'] === 'draft' ? null : (int) $cluster['id'],
                'unfilled_vacancies' => $cluster['unfilled_vacancies'] ?? [],
                'conflict_count' => $this->conflictCount($indices),
                'in_diagram' => true,
            ];
        }

        $orphans = array_values(array_filter($allIndices, fn ($idx) => ! isset($assigned[$idx])));
        if (! empty($orphans)) {
            $groups[] = [
                'key' => 'belum-terhubung',
                'title' => empty($groups) ? 'Tahap Penyusunan' : 'Belum Masuk Alur Mutasi',
                'type' => 'draft',
                'is_zero_vacancy' => false,
                'is_loose' => ! empty($groups),
                'indices' => $orphans,
                'cluster_id' => null,
                'unfilled_vacancies' => [],
                'conflict_count' => $this->conflictCount($orphans),
                'in_diagram' => false,
            ];
        }

        return $groups;
    }

    /**
     * Slots in this group whose target formation is held by someone outside the chain.
     *
     * @param  array<int, int>  $indices
     */
    private function conflictCount(array $indices): int
    {
        return count(array_filter($indices, fn (int $idx) => ($this->nodes[$idx]['link_type'] ?? null) === 'conflict'));
    }

    /**
     * Get smart filtered target positions for a specific node based on unit & employee RM.
     */
    public function getAvailableTargetPositionsForNode(int $index): array
    {
        if (! isset($this->nodes[$index])) {
            return [];
        }

        $node = $this->nodes[$index];
        $targetUnit = $node['target_unit_kerja'] ?? '';
        $nik = $node['employee_nik'] ?? '';

        if (! $targetUnit || ! $nik) {
            return [];
        }

        return $this->service()->listTargetPositions($nik, $targetUnit, $node['jenis_mutasi'] ?? 'MUTASI_UNIT');
    }

    /**
     * Validate header and trigger review summary modal. The modal's confirm button follows reviewMode
     * (issue SK, submit for approval, or approve).
     */
    public function openReviewModal(): void
    {
        if ($this->reviewMode === 'none') {
            $this->addError('chain_topology', 'Anda tidak memiliki tindakan yang tersedia untuk usulan ini.');

            return;
        }

        if ($this->passesReview()) {
            $this->showReviewModal = true;
        }
    }

    /**
     * Every check that must pass before an SK can be issued or submitted: SK header, chain validity and
     * Direksi dispensation evidence. Errors are put on the error bag.
     */
    private function passesReview(): bool
    {
        $this->resetErrorBag();

        // 1. Validate Header
        $rules = [
            'nomorSk' => 'required|string|max:100',
            'tanggalSk' => 'required|date',
            'tmtAwal' => 'required|date',
            'fileSk' => 'nullable|file|mimes:pdf|max:10240',
        ];

        $messages = [
            'nomorSk.required' => 'Nomor SK Direksi Kolektif wajib diisi.',
            'tanggalSk.required' => 'Tanggal penetapan SK wajib diisi.',
            'tmtAwal.required' => 'TMT efektif mutasi wajib diisi.',
            'fileSk.mimes' => 'Berkas lampiran SK harus berformat PDF.',
            'fileSk.max' => 'Ukuran berkas PDF maksimal 10 MB.',
        ];

        $this->validate($rules, $messages);

        // Component state is client-mutable: re-derive every node's dispensation from its evidence before any check
        foreach (array_keys($this->nodes) as $idx) {
            $this->syncDispensasi($idx);
        }

        // 2. Validate Topology
        $this->runTopologyAnalysis();

        if (! $this->chainAnalysis || ! $this->chainAnalysis['is_valid']) {
            $errText = ! empty($this->chainAnalysis['errors'])
                ? implode(' ', $this->chainAnalysis['errors'])
                : 'Konfigurasi rantai mutasi belum valid.';
            $this->addError('chain_topology', $errText);

            return false;
        }

        // 3. Validate Dispensations for non-compliant nodes
        $hasComplianceErrors = false;
        foreach ($this->nodes as $idx => $node) {
            if (empty($node['employee_nik']) || empty($node['target_jabatan']) || empty($node['target_unit_kerja'])) {
                continue;
            }

            $isTenureNonCompliant = ! empty($node['tenure_analysis']) && ! ($node['tenure_analysis']['is_compliant'] ?? true) && ($node['tenure_analysis']['is_applicable'] ?? true);
            $isPromoNonCompliant = ! empty($node['promotion_analysis'])
                && ($node['promotion_analysis']['is_applicable'] ?? false)
                && ! ($node['promotion_analysis']['is_eligible'] ?? true);

            if (! $isTenureNonCompliant && ! $isPromoNonCompliant) {
                continue;
            }

            // One Direksi dispensation per node must cover every rule that is not met: document reference + approval date
            if (! DispensasiBasis::hasReference($node['alasan_dispensasi_direksi'] ?? null)) {
                $this->addError("nodes.{$idx}.alasan_dispensasi_direksi", 'Nomor nota dinas / SK persetujuan Direksi wajib diisi (minimal '.DispensasiBasis::MIN_REFERENCE_LENGTH.' karakter) karena terdapat pengecualian regulasi.');
                $hasComplianceErrors = true;
            }

            if (! DispensasiBasis::hasValidDate($node['tanggal_dispensasi_direksi'] ?? null)) {
                $this->addError("nodes.{$idx}.tanggal_dispensasi_direksi", 'Tanggal persetujuan Direksi wajib diisi dan tidak boleh melewati hari ini.');
                $hasComplianceErrors = true;
            }
        }

        if ($hasComplianceErrors) {
            $this->addError('chain_topology', 'Terdapat persyaratan regulasi (masa kerja atau kualifikasi promosi) pada mata rantai yang memerlukan persetujuan dispensasi Direksi.');

            return false;
        }

        return true;
    }

    public function closeReviewModal(): void
    {
        $this->showReviewModal = false;
    }

    /**
     * Open Slide-Over Talent Intelligence Drawer for a specific participant.
     */
    public function openTalentDrawer(string $nik, MutasiService $service): void
    {
        $this->selectedTalentNik = $nik;
        $this->selectedTalentData = $service->getTalentProfile($nik);
        $this->showTalentDrawer = true;
    }

    /**
     * Close Slide-Over Talent Intelligence Drawer.
     */
    public function closeTalentDrawer(): void
    {
        $this->showTalentDrawer = false;
        $this->selectedTalentNik = null;
        $this->selectedTalentData = null;
    }

    /**
     * Issue the SK directly (four-eyes policy off). When the chain came from a saved draft, that draft is
     * closed as executed in the same transaction.
     */
    public function submitChain(ChainProposalService $proposals): void
    {
        if ($this->reviewMode !== 'execute') {
            $this->showReviewModal = false;
            $this->addError('chain_topology', ChainProposalService::requiresApproval()
                ? 'Kebijakan persetujuan berjenjang aktif: ajukan usulan ini untuk disetujui admin lain.'
                : 'Akun Anda tidak berwenang menerbitkan SK mutasi.');

            return;
        }

        $this->assertUnlocked();
        $this->openReviewModal();
        if (! $this->showReviewModal) {
            return;
        }

        $headerData = $this->headerData() + [
            // Upload file SK to private disk (SECURITY.md §4.4); a draft keeps the file it already stored
            'file_sk' => $this->fileSk ? $proposals->storeFile($this->fileSk) : $this->proposal?->file_sk,
            'created_by' => auth()->id(),
        ];

        try {
            // The service keeps complete participants only and folds the dispensation fields itself
            $result = $proposals->executeDirect($this->proposal, auth()->user(), $headerData, $this->nodes);
        } catch (MutasiRuleViolationException|MutasiIntegrityException $e) {
            $this->showReviewModal = false;
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        $this->finishWithSuccess($result);
    }

    /** Save the current inputs as a draft (field formats only; nothing is executed). */
    public function saveDraft(ChainProposalService $proposals): void
    {
        $this->assertUnlocked();
        $this->resetErrorBag();
        $this->validate($this->draftRules(), $this->draftMessages());

        $isNew = $this->proposalId === null;
        $proposal = $this->persistDraft($proposals);

        $this->notice = 'Draf usulan tersimpan pada '.now()->translatedFormat('d M Y H:i').'. Lanjutkan kapan saja dari tab Usulan Berantai.';
        if ($isNew) {
            // Keep the URL reloadable without a full page refresh
            $this->js('history.replaceState(null, "", '.json_encode(route('admin.mutasi.chain', ['usulan' => $proposal->id])).')');
        }
    }

    /** Four-eyes policy: save the latest inputs and submit them for another admin to approve. */
    public function submitForApproval(ChainProposalService $proposals): void
    {
        $this->assertUnlocked();
        if ($this->reviewMode !== 'submit' || ! $this->passesReview()) {
            $this->showReviewModal = false;

            return;
        }

        try {
            $proposal = $this->persistDraft($proposals);
            $proposals->submit($proposal, auth()->user());
        } catch (MutasiRuleViolationException $e) {
            $this->showReviewModal = false;
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        session()->flash('mutasi_success', "Usulan mutasi berantai {$proposal->participant_count} karyawan telah diajukan dan menunggu persetujuan admin lain.");
        $this->redirect(route('admin.mutasi.chain', ['usulan' => $proposal->id]), navigate: false);
    }

    /** The maker pulls a pending proposal back to draft. */
    public function withdrawProposal(ChainProposalService $proposals): void
    {
        $proposal = $this->proposal;
        abort_unless($proposal, 404);

        try {
            $proposals->withdraw($proposal, auth()->user());
        } catch (MutasiRuleViolationException $e) {
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        session()->flash('mutasi_success', 'Usulan ditarik kembali menjadi draf dan dapat diubah.');
        $this->redirect(route('admin.mutasi.chain', ['usulan' => $proposal->id]), navigate: false);
    }

    /**
     * The checker approves: inputs are reloaded from the database (never from client state), every check
     * runs again on current data, and the SK is issued only if the submitted fingerprint still matches.
     */
    public function approveProposal(ChainProposalService $proposals): void
    {
        unset($this->proposal, $this->isLocked, $this->reviewMode);
        $proposal = $this->proposal;
        abort_unless($proposal, 404);

        if ($this->reviewMode !== 'approve') {
            $this->showReviewModal = false;
            $this->addError('chain_topology', 'Usulan ini tidak dapat Anda setujui. Usulan harus diputuskan oleh admin lain yang berwenang.');

            return;
        }

        $this->loadProposalState($proposal);
        if (! $this->passesReview()) {
            $this->showReviewModal = false;

            return;
        }

        try {
            $result = $proposals->approve($proposal, auth()->user(), $this->nodes);
        } catch (MutasiRuleViolationException|MutasiIntegrityException $e) {
            $this->showReviewModal = false;
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        $this->finishWithSuccess($result);
    }

    public function openRejectModal(): void
    {
        $this->resetErrorBag('rejectReason');
        $this->rejectReason = '';
        $this->showRejectModal = true;
    }

    public function closeRejectModal(): void
    {
        $this->showRejectModal = false;
    }

    /** The checker returns the proposal to its maker with a reason. */
    public function rejectProposal(ChainProposalService $proposals): void
    {
        $proposal = $this->proposal;
        abort_unless($proposal, 404);

        $this->validate(
            ['rejectReason' => 'required|string|min:'.ChainProposalService::MIN_REASON_LENGTH.'|max:1000'],
            [
                'rejectReason.required' => 'Alasan pengembalian wajib diisi.',
                'rejectReason.min' => 'Alasan pengembalian minimal '.ChainProposalService::MIN_REASON_LENGTH.' karakter agar penyusun tahu apa yang perlu diperbaiki.',
            ]
        );

        try {
            $proposals->reject($proposal, auth()->user(), $this->rejectReason);
        } catch (MutasiRuleViolationException $e) {
            $this->showRejectModal = false;
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        session()->flash('mutasi_notice', 'Usulan dikembalikan kepada penyusun untuk direvisi.');
        $this->redirect(route('admin.mutasi.index', ['tab' => 'usulan']), navigate: false);
    }

    /** Delete a draft that was never submitted. */
    public function discardDraft(ChainProposalService $proposals): void
    {
        $proposal = $this->proposal;
        abort_unless($proposal, 404);

        try {
            $proposals->discard($proposal, auth()->user());
        } catch (MutasiRuleViolationException $e) {
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        session()->flash('mutasi_notice', 'Draf usulan dihapus.');
        $this->redirect(route('admin.mutasi.index', ['tab' => 'usulan']), navigate: false);
    }

    /**
     * Add a slot whose target is a vacancy this chain leaves behind (Isi Lowongan Tersisa). The vacancy is
     * looked up in the analysis; the admin only has to pick who fills it.
     */
    public function fillVacancy(int $clusterId, int $vacancyIndex = 0): void
    {
        $this->assertUnlocked();

        $cluster = collect($this->chainAnalysis['clusters'] ?? [])->firstWhere('id', $clusterId);
        $vacancy = $cluster['unfilled_vacancies'][$vacancyIndex] ?? null;
        if (! $vacancy) {
            return;
        }

        $this->addNode(null, $vacancy['jabatan'], $vacancy['unit_kerja']);
        $this->dispatch('chain-slot-added', index: count($this->nodes) - 1);
    }

    // -------------------------------------------------------------------------

    /** @return array{nomor_sk: string, tanggal_sk: string, tmt_awal: string, catatan: string} */
    private function headerData(): array
    {
        return [
            'nomor_sk' => $this->nomorSk,
            'tanggal_sk' => $this->tanggalSk,
            'tmt_awal' => $this->tmtAwal,
            'catatan' => $this->catatanHeader,
        ];
    }

    private function persistDraft(ChainProposalService $proposals): MutasiChainProposal
    {
        $header = $this->headerData();
        if ($this->fileSk) {
            $header['file_sk'] = $proposals->storeFile($this->fileSk);
        }

        $proposal = $proposals->saveDraft($this->proposal, $header, $this->nodes, auth()->user());
        $this->proposalId = $proposal->id;
        $this->fileSk = null;
        unset($this->proposal, $this->isLocked, $this->reviewMode);

        return $proposal;
    }

    /** @return array<string, string> */
    private function draftRules(): array
    {
        return [
            'nomorSk' => 'nullable|string|max:100',
            'tanggalSk' => 'nullable|date',
            'tmtAwal' => 'nullable|date',
            'catatanHeader' => 'nullable|string|max:1000',
            'fileSk' => 'nullable|file|mimes:pdf|max:10240',
        ];
    }

    /** @return array<string, string> */
    private function draftMessages(): array
    {
        return [
            'fileSk.mimes' => 'Berkas lampiran SK harus berformat PDF.',
            'fileSk.max' => 'Ukuran berkas PDF maksimal 10 MB.',
        ];
    }

    private function finishWithSuccess(array $result): void
    {
        $topologyText = $result['topology_label'] ?? '';
        $msg = "Sukses! Rantai mutasi kolektif {$result['total_transfers']} karyawan berhasil diproses secara serentak [{$topologyText}]. Nomor SK: {$result['nomor_sk']}.";

        session()->flash('mutasi_success', $msg);
        $this->redirect(route('admin.mutasi.index', ['tab' => 'history']), navigate: false);
    }

    public function render(MutasiService $service)
    {
        return view('livewire.admin.mutasi-chain-process', [
            'allEmployees' => $service->getSelectableEmployees(),
            'groupedUnits' => $service->getGroupedUnits(),
        ]);
    }
}
