<?php

namespace App\Livewire\Admin;

use App\Exceptions\MutasiIntegrityException;
use App\Exceptions\MutasiRuleViolationException;
use App\Services\Mutasi\MutasiPolicy;
use App\Services\MutasiService;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class MutasiChainProcess extends Component
{
    use WithFileUploads;

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

    public function mount(MutasiService $service): void
    {
        $this->tanggalSk = Carbon::today()->toDateString();
        // Default TMT: first day of next month
        $this->tmtAwal = Carbon::now()->addMonth()->startOfMonth()->toDateString();

        $this->unitsList = $service->getKnownUnits()->toArray();

        // Initialize with 2 empty node rows
        $this->addNode();
        $this->addNode();
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
        if (count($this->nodes) <= MutasiPolicy::MIN_CHAIN_PARTICIPANTS) {
            $this->addError('nodes', 'Rantai mutasi kolektif memerlukan minimal '.MutasiPolicy::MIN_CHAIN_PARTICIPANTS.' pegawai.');

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
                if (! ($this->nodes[$index]['promotion_eligible'] ?? true)) {
                    $this->nodes[$index]['dispensasi_promosi'] = true;
                }
            } else {
                $this->nodes[$index]['dispensasi_tenure'] = false;
                $this->nodes[$index]['dispensasi_promosi'] = false;
            }
        } elseif ($field === 'dispensasi_tenure' || $field === 'dispensasi_promosi') {
            $this->nodes[$index]['is_dispensasi'] = ! empty($this->nodes[$index]['dispensasi_tenure']) || ! empty($this->nodes[$index]['dispensasi_promosi']);
        }

        $this->evaluateNode($index);
        $this->runTopologyAnalysis();
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
        $targetAnalysis = $service->analyzeTarget($targetJabatan, $targetUnit);
        if ($targetAnalysis['type'] === 'pool') {
            $node['occupant_nik'] = null;
            $node['occupant_nama'] = null;
            $node['link_type'] = 'pool';
        } elseif ($targetAnalysis['vacant']) {
            $node['occupant_nik'] = null;
            $node['occupant_nama'] = null;
            $node['link_type'] = 'vacant';
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
                'errors' => ['Lengkapi minimal '.MutasiPolicy::MIN_CHAIN_PARTICIPANTS.' pegawai beserta formasi dan unit kerja target.'],
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
            }

            return $clusters;
        }

        // Fallback for draft phase before full validation: single draft cluster
        $ordered = $this->getOrderedFlowNodes();
        if (empty($ordered)) {
            return [];
        }

        return [
            [
                'id' => 1,
                'type' => 'draft',
                'title' => 'Klaster Alur Mutasi (Tahap Penyusunan)',
                'type_label' => 'Tahap Penyusunan',
                'is_zero_vacancy' => false,
                'nodes' => $ordered,
                'loopback' => null,
                'closed_vacancies' => [],
                'unfilled_vacancies' => [],
            ],
        ];
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
     * Validate header and trigger review summary modal.
     */
    public function openReviewModal(): void
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

        // 2. Validate Topology
        $this->runTopologyAnalysis();

        if (! $this->chainAnalysis || ! $this->chainAnalysis['is_valid']) {
            $errText = ! empty($this->chainAnalysis['errors'])
                ? implode(' ', $this->chainAnalysis['errors'])
                : 'Konfigurasi rantai mutasi belum valid.';
            $this->addError('chain_topology', $errText);

            return;
        }

        // 3. Validate Dispensations for non-compliant nodes
        $hasComplianceErrors = false;
        foreach ($this->nodes as $idx => $node) {
            if (empty($node['employee_nik']) || empty($node['target_jabatan']) || empty($node['target_unit_kerja'])) {
                continue;
            }

            $isTenureNonCompliant = ! empty($node['tenure_analysis']) && ! ($node['tenure_analysis']['is_compliant'] ?? true) && ($node['tenure_analysis']['is_applicable'] ?? true);
            $isPromoNonCompliant = ! empty($node['promotion_analysis']) && ! ($node['promotion_analysis']['is_eligible'] ?? true) && ($node['promotion_analysis']['is_applicable'] ?? false);

            if ($isTenureNonCompliant && empty($node['dispensasi_tenure']) && empty($node['is_dispensasi'])) {
                $this->addError("nodes.{$idx}.dispensasi_tenure", 'Masa dinas di unit belum mencapai 2 tahun. Wajib menyetujui dispensasi khusus direksi.');
                $hasComplianceErrors = true;
            }

            if ($isPromoNonCompliant && empty($node['dispensasi_promosi']) && empty($node['is_dispensasi'])) {
                $this->addError("nodes.{$idx}.dispensasi_promosi", 'Belum memenuhi kualifikasi promosi. Wajib menyetujui dispensasi promosi direksi.');
                $hasComplianceErrors = true;
            }
        }

        if ($hasComplianceErrors) {
            $this->addError('chain_topology', 'Terdapat persyaratan regulasi masa kerja atau promosi pada mata rantai yang memerlukan persetujuan dispensasi direksi.');

            return;
        }

        $this->showReviewModal = true;
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
     * Atomically execute the chain mutation.
     */
    public function submitChain(MutasiService $service): void
    {
        $this->openReviewModal();
        if (! $this->showReviewModal) {
            return;
        }

        // Upload file SK to private disk (SECURITY.md §4.4)
        $fileSkPath = null;
        if ($this->fileSk) {
            $fileSkPath = $this->fileSk->store('dokumen_sk', 'local');
        }

        $headerData = [
            'nomor_sk' => $this->nomorSk,
            'tanggal_sk' => $this->tanggalSk,
            'tmt_awal' => $this->tmtAwal,
            'file_sk' => $fileSkPath,
            'created_by' => auth()->id(),
            'catatan' => $this->catatanHeader,
        ];

        try {
            // The service keeps complete participants only and folds the dispensation fields itself
            $result = $service->processChainMutasi($headerData, $this->nodes);
        } catch (MutasiRuleViolationException|MutasiIntegrityException $e) {
            $this->showReviewModal = false;
            $this->addError('chain_topology', $e->getMessage());

            return;
        }

        $topologyText = $result['topology_label'] ?? ($result['topology'] === 'cyclic' ? 'Siklis Tertutup (Net Zero Vacancy)' : 'Rantai Domino Terbuka');
        $msg = "Sukses! Rantai mutasi kolektif {$result['total_transfers']} pegawai berhasil diproses secara serentak [{$topologyText}]. Nomor SK: {$result['nomor_sk']}.";

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
