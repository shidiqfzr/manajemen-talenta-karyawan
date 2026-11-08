<?php

namespace App\Services\Mutasi;

use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use Carbon\Carbon;

/**
 * Graph analysis of a collective SK (chain mutation): cyclic loops (net zero vacancy),
 * open domino chains, target conflicts and per-participant compliance (DOMAIN.md §5.2.2).
 */
class ChainTopologyAnalyzer
{
    public function __construct(
        private readonly FormationService $formations,
        private readonly MutasiGovernanceGuard $guard,
    ) {}

    /**
     * Keep only complete participants (employee, target position & unit) and fold the
     * per-criterion dispensation fields of the form into one flag + one written reason.
     *
     * @return list<array<string, mixed>>
     */
    public function prepareNodes(array $nodes): array
    {
        $complete = array_filter(
            $nodes,
            fn (array $n) => ! empty($n['employee_nik']) && ! empty($n['target_jabatan']) && ! empty($n['target_unit_kerja'])
        );

        return array_map(function (array $node) {
            $reasons = [];
            if (! empty($node['dispensasi_tenure']) && ! empty($node['alasan_dispensasi_tenure'])) {
                $reasons[] = '[Dispensasi Masa Dinas]: '.$node['alasan_dispensasi_tenure'];
            }
            if (! empty($node['dispensasi_promosi']) && ! empty($node['alasan_dispensasi_promosi'])) {
                $reasons[] = '[Dispensasi Promosi]: '.$node['alasan_dispensasi_promosi'];
            }
            if (empty($reasons) && ! empty($node['alasan_dispensasi'])) {
                $reasons[] = $node['alasan_dispensasi'];
            }

            $node['is_dispensasi'] = ! empty($node['is_dispensasi']) || ! empty($node['dispensasi_tenure']) || ! empty($node['dispensasi_promosi']);
            $node['alasan_dispensasi'] = $reasons ? implode(' | ', $reasons) : null;

            return $node;
        }, array_values($complete));
    }

    /**
     * Order participants by causal domino flow (directed graph: node U targets the seat of node V)
     * for the topology flowchart. Heads (nobody takes their seat) come first; cycles follow.
     * Each node gets original_slot_index (0-based) and slot_number (1-based).
     *
     * @return list<array<string, mixed>>
     */
    public function orderNodesByFlow(array $nodes): array
    {
        if (empty($nodes)) {
            return [];
        }

        $indexedNodes = [];
        $nikToNodeIndex = [];
        $sourceToTargetOccupant = [];
        $targetOccupantToSourceIndex = [];

        foreach ($nodes as $idx => $node) {
            $indexedNodes[$idx] = array_merge($node, [
                'original_slot_index' => $idx,
                'slot_number' => $idx + 1,
            ]);
            if (! empty($node['employee_nik'])) {
                $nikToNodeIndex[$node['employee_nik']] = $idx;
            }
        }

        // Build edges: Node U targets position occupied by Node V
        foreach ($indexedNodes as $idx => $node) {
            $occ = $node['occupant_nik'] ?? null;
            if ($occ && isset($nikToNodeIndex[$occ]) && $nikToNodeIndex[$occ] !== $idx) {
                $targetIdx = $nikToNodeIndex[$occ];
                $sourceToTargetOccupant[$idx] = $targetIdx;
                $targetOccupantToSourceIndex[$targetIdx][] = $idx;
            }
        }

        // Heads: nodes whose seat nobody in the chain takes, so their departure opens a new vacancy
        $heads = array_filter(
            array_keys($indexedNodes),
            fn ($idx) => empty($targetOccupantToSourceIndex[$idx])
        );

        $orderedIndices = [];
        $visited = [];

        // Traverse from head nodes first (domino flow), then the remaining (cyclic) nodes
        foreach ([...$heads, ...array_keys($indexedNodes)] as $startIdx) {
            $curr = $startIdx;
            while ($curr !== null && ! in_array($curr, $visited, true)) {
                $visited[] = $curr;
                $orderedIndices[] = $curr;
                $curr = $sourceToTargetOccupant[$curr] ?? null;
            }
        }

        return array_map(fn ($idx) => $indexedNodes[$idx], $orderedIndices);
    }

    /**
     * Validate the entire topology of a multi-node chain transfer.
     * Detects cyclic loops (zero net vacancy), open domino chains, target conflicts, and compliance.
     */
    public function validateChainTopology(array $nodes): array
    {
        $errors = [];
        $warnings = [];
        $nodesAnalysis = [];

        if (count($nodes) < MutasiPolicy::MIN_CHAIN_PARTICIPANTS) {
            return [
                'is_valid' => false,
                'topology' => 'invalid',
                'topology_label' => 'Rantai Tidak Lengkap',
                'errors' => ['Rantai mutasi kolektif memerlukan minimal '.MutasiPolicy::MIN_CHAIN_PARTICIPANTS.' karyawan.'],
                'warnings' => [],
                'nodes_analysis' => [],
                'unfilled_vacancies' => [],
                'closed_vacancies' => [],
            ];
        }

        $niks = array_column($nodes, 'employee_nik');
        if (count($niks) !== count(array_unique($niks))) {
            $errors[] = 'Terdapat duplikasi karyawan di dalam daftar rantai mutasi.';
        }

        $employees = Employee::with('currentJob')
            ->whereIn('nik', $niks)
            ->get()
            ->keyBy('nik');

        // Check if all employees exist and are active (not retired)
        foreach ($niks as $nik) {
            if (! isset($employees[$nik])) {
                $errors[] = "Data karyawan dengan NIK {$nik} tidak ditemukan.";
            } elseif ($employees[$nik]->tanggal_pensiun && Carbon::parse($employees[$nik]->tanggal_pensiun)->lt(now()->startOfMonth())) {
                $errors[] = "Karyawan {$employees[$nik]->nama} (NIK: {$nik}) telah memasuki Batas Usia Pensiun dan tidak dapat dimutasi.";
            }
        }

        if (! empty($errors)) {
            return [
                'is_valid' => false,
                'topology' => 'invalid',
                'topology_label' => 'Data Karyawan Tidak Valid',
                'errors' => $errors,
                'warnings' => [],
                'nodes_analysis' => [],
                'unfilled_vacancies' => [],
                'closed_vacancies' => [],
            ];
        }

        $positions = Position::getAllCached();
        $targetPositions = [];
        $closedVacancies = [];
        $unfilledVacancies = [];

        // Pj/Plt participants keep their definitive seat, so their current position is NOT released
        $temporaryNiks = [];
        foreach ($nodes as $node) {
            if (($node['status_jabatan'] ?? 'Definitif') !== 'Definitif') {
                $temporaryNiks[] = $node['employee_nik'];
            }
        }

        // Check target position conflicts (cannot have 2 people targeting the same definitive position)
        foreach ($nodes as $idx => $node) {
            $emp = $employees[$node['employee_nik']];
            $targetJabatan = $node['target_jabatan'];
            $targetUnitKerja = $node['target_unit_kerja'];
            $posKey = "{$targetJabatan}|{$targetUnitKerja}";

            $jenisMutasi = $node['jenis_mutasi'] ?? 'MUTASI_UNIT';
            if (! in_array($jenisMutasi, JobHistory::MUTASI_TYPES, true)) {
                $errors[] = "Jenis mutasi '{$jenisMutasi}' untuk karyawan {$emp->nama} tidak dikenal dalam Pedoman Tata Kelola SDM.";
            }

            $isPool = isset($positions[$targetJabatan]) && ($positions[$targetJabatan]->is_overflow || $positions[$targetJabatan]->is_diperban);

            // Self-target guard
            if ($emp->jabatan === $targetJabatan && $emp->unit_kerja === $targetUnitKerja) {
                $errors[] = "Karyawan {$emp->nama} sudah menempati posisi {$targetJabatan} di {$targetUnitKerja}.";
            }

            // Duplicate target guard (unless pool)
            if (! $isPool) {
                if (isset($targetPositions[$posKey])) {
                    $errors[] = "Konflik target: Formasi {$targetJabatan} di {$targetUnitKerja} ditargetkan oleh lebih dari satu karyawan.";
                }
                $targetPositions[$posKey] = $node['employee_nik'];
            }

            // Analyze target vacancy and occupancy
            $analysis = $this->formations->analyzeTarget($targetJabatan, $targetUnitKerja);
            $occupant = $analysis['occupant'] ?? null;
            $linkType = 'vacant';
            $occupantNik = null;
            $occupantNama = null;

            if ($analysis['type'] === 'pool') {
                $linkType = 'pool';
            } elseif ($analysis['is_reserved'] && ! in_array($analysis['reserved_job']->employee_nik, $niks, true)) {
                $resName = $analysis['reserved_occupant']->nama ?? $analysis['reserved_job']->employee_nik;
                $resTmt = $analysis['reserved_job']->tmt_awal ? Carbon::parse($analysis['reserved_job']->tmt_awal)->translatedFormat('d M Y') : 'mendatang';
                $linkType = 'conflict';
                $errors[] = "Formasi target {$targetJabatan} di {$targetUnitKerja} telah dipesan (Reserved) oleh mutasi terjadwal atas nama {$resName} (TMT aktif: {$resTmt}).";
            } elseif ($occupant === null) {
                $linkType = 'vacant';
                $closedVacancies[] = [
                    'jabatan' => $targetJabatan,
                    'unit_kerja' => $targetUnitKerja,
                    'filled_by' => $emp->nama,
                    'filled_by_nik' => $emp->nik,
                ];
            } else {
                $occupantNik = $occupant->nik;
                $occupantNama = $occupant->nama;

                // Is occupant part of this chain?
                if (in_array($occupantNik, $temporaryNiks, true)) {
                    $linkType = 'conflict';
                    $errors[] = "Formasi target {$targetJabatan} di {$targetUnitKerja} tidak dilepaskan karena {$occupantNama} hanya menjalani penugasan sementara (Pj/Plt) dalam rantai ini.";
                } elseif (in_array($occupantNik, $niks, true)) {
                    $linkType = 'chain'; // Valid link: occupant will also move!
                } else {
                    $linkType = 'conflict';
                    $errors[] = "Formasi target {$targetJabatan} di {$targetUnitKerja} saat ini diduduki oleh {$occupantNama} ({$occupantNik}) yang tidak diikutsertakan dalam rantai mutasi ini. Masukkan karyawan tersebut ke dalam rantai mutasi atau pilih formasi lain.";
                }
            }

            // Governance: Tenure compliance
            $tenure = $this->guard->checkTenureCompliance($emp, $targetUnitKerja);
            $isDispensasi = ! empty($node['is_dispensasi']);
            $alasanDispensasi = $node['alasan_dispensasi'] ?? null;

            if ($tenure['is_applicable'] && ! $tenure['is_compliant']) {
                if (! $isDispensasi) {
                    $errors[] = "Karyawan {$emp->nama} belum memenuhi masa dinas minimal di {$emp->unit_kerja} ({$tenure['months']} bulan). Harap setujui dispensasi masa dinas.";
                } else {
                    $warnings[] = "Karyawan {$emp->nama} menggunakan Dispensasi Masa Dinas di {$emp->unit_kerja}.";
                }
            }

            // Governance: Promotion eligibility
            $promo = $this->guard->checkPromotionEligibility($emp, $targetJabatan);
            if ($promo['is_applicable'] && ! $promo['is_eligible']) {
                if (! $isDispensasi) {
                    $errors[] = "Karyawan {$emp->nama} belum memenuhi kriteria promosi ke {$targetJabatan}. Harap setujui dispensasi promosi.";
                } else {
                    $warnings[] = "Karyawan {$emp->nama} dipromosikan dengan Dispensasi Khusus Direksi ke {$targetJabatan}.";
                }
            }

            $nodesAnalysis[] = [
                'employee_nik' => $emp->nik,
                'nama' => $emp->nama,
                'current_jabatan' => $emp->jabatan,
                'current_unit' => $emp->unit_kerja,
                'current_rm' => $emp->rm_level,
                'target_jabatan' => $targetJabatan,
                'target_unit' => $targetUnitKerja,
                'link_type' => $linkType,
                'occupant_nik' => $occupantNik,
                'occupant_nama' => $occupantNama,
                'tenure' => $tenure,
                'promotion' => $promo,
                'is_dispensasi' => $isDispensasi,
                'alasan_dispensasi' => $alasanDispensasi,
            ];
        }

        // Detect positions vacated by participants that are NOT filled by anyone in the chain
        foreach ($nodes as $node) {
            $emp = $employees[$node['employee_nik']];
            $vacatedKey = "{$emp->jabatan}|{$emp->unit_kerja}";

            if (! isset($targetPositions[$vacatedKey]) && ! in_array($emp->nik, $temporaryNiks, true)) {
                $unfilledVacancies[] = [
                    'jabatan' => $emp->jabatan,
                    'unit_kerja' => $emp->unit_kerja,
                    'left_by' => $emp->nama,
                    'left_by_nik' => $emp->nik,
                ];
            }
        }

        // ── Graph Decomposition into Weakly Connected Components (Enterprise Multi-Cluster) ──
        $clusters = [];
        $cycleClusterCount = 0;
        $dominoClusterCount = 0;
        $openClusterCount = 0;

        if (empty($errors)) {
            $nikToNodeIdx = [];
            foreach ($nodesAnalysis as $idx => $nodeData) {
                $nikToNodeIdx[$nodeData['employee_nik']] = $idx;
            }

            $adj = []; // $u => $v where node $u targets occupant $v
            $revAdj = []; // $v => [$u, ...]
            $undirected = [];

            foreach ($nodesAnalysis as $idx => $nodeData) {
                $occNik = $nodeData['occupant_nik'] ?? null;
                if ($occNik && isset($nikToNodeIdx[$occNik]) && $nikToNodeIdx[$occNik] !== $idx) {
                    $targetIdx = $nikToNodeIdx[$occNik];
                    $adj[$idx] = $targetIdx;
                    $revAdj[$targetIdx][] = $idx;
                    $undirected[$idx][] = $targetIdx;
                    $undirected[$targetIdx][] = $idx;
                }
            }

            // Decompose into Weakly Connected Components via BFS
            $visited = [];
            $rawComponents = [];

            foreach (array_keys($nodesAnalysis) as $idx) {
                if (isset($visited[$idx])) {
                    continue;
                }

                $comp = [];
                $queue = [$idx];
                $visited[$idx] = true;

                while (! empty($queue)) {
                    $curr = array_shift($queue);
                    $comp[] = $curr;

                    foreach ($undirected[$curr] ?? [] as $neighbor) {
                        if (! isset($visited[$neighbor])) {
                            $visited[$neighbor] = true;
                            $queue[] = $neighbor;
                        }
                    }
                }

                $rawComponents[] = $comp;
            }

            foreach ($rawComponents as $compIdx => $compNodeIndices) {
                $compSize = count($compNodeIndices);

                // Internal edges inside this component
                $internalIn = [];
                $internalOut = [];
                foreach ($compNodeIndices as $idx) {
                    if (isset($adj[$idx]) && in_array($adj[$idx], $compNodeIndices, true)) {
                        $internalOut[$idx] = $adj[$idx];
                    }
                    foreach ($revAdj[$idx] ?? [] as $src) {
                        if (in_array($src, $compNodeIndices, true)) {
                            $internalIn[$idx][] = $src;
                        }
                    }
                }

                // Check if this component is a pure closed cycle
                $isCompCyclic = ($compSize >= 2);
                if ($isCompCyclic) {
                    foreach ($compNodeIndices as $idx) {
                        if (! isset($internalOut[$idx]) || count($internalIn[$idx] ?? []) !== 1) {
                            $isCompCyclic = false;
                            break;
                        }
                    }
                }

                $orderedIndices = [];
                $loopback = null;
                $clusterType = 'complex';
                $clusterLabel = "Klaster Majemuk ({$compSize} Karyawan)";

                if ($isCompCyclic) {
                    $clusterType = 'cyclic';
                    $cycleClusterCount++;
                    $clusterLabel = $compSize === 2
                        ? 'Tukar Jabatan / Swap (2 Karyawan)'
                        : "Rantai Siklis ({$compSize} Karyawan)";

                    // Order sequentially starting from node with lowest original index
                    $start = min($compNodeIndices);
                    $curr = $start;
                    $loopVisited = [];
                    while ($curr !== null && ! in_array($curr, $loopVisited, true)) {
                        $loopVisited[] = $curr;
                        $orderedIndices[] = $curr;
                        $curr = $internalOut[$curr] ?? null;
                    }

                    $headIdx = $orderedIndices[0];
                    $tailIdx = $orderedIndices[count($orderedIndices) - 1];
                    $loopback = [
                        'from_nik' => $nodesAnalysis[$tailIdx]['employee_nik'],
                        'from_nama' => $nodesAnalysis[$tailIdx]['nama'],
                        'to_nik' => $nodesAnalysis[$headIdx]['employee_nik'],
                        'to_nama' => $nodesAnalysis[$headIdx]['nama'],
                        'to_jabatan' => $nodesAnalysis[$headIdx]['current_jabatan'],
                        'to_unit' => $nodesAnalysis[$headIdx]['current_unit'],
                    ];
                } else {
                    // Domino or Open: find heads (in-degree 0 inside component)
                    $heads = [];
                    foreach ($compNodeIndices as $idx) {
                        if (empty($internalIn[$idx])) {
                            $heads[] = $idx;
                        }
                    }
                    if (empty($heads)) {
                        $heads = [min($compNodeIndices)];
                    }

                    $compVisited = [];
                    foreach ($heads as $h) {
                        $curr = $h;
                        while ($curr !== null && ! in_array($curr, $compVisited, true)) {
                            $compVisited[] = $curr;
                            $orderedIndices[] = $curr;
                            $curr = $internalOut[$curr] ?? null;
                        }
                    }

                    // Add leftovers if any
                    foreach ($compNodeIndices as $idx) {
                        if (! in_array($idx, $compVisited, true)) {
                            $orderedIndices[] = $idx;
                        }
                    }

                    if ($compSize >= 2) {
                        $clusterType = 'domino';
                        $dominoClusterCount++;
                        $clusterLabel = "Alur Domino ({$compSize} Karyawan)";
                    } else {
                        $clusterType = 'open';
                        $openClusterCount++;
                        $clusterLabel = 'Transfer Mandiri (1 Karyawan)';
                    }
                }

                // Cluster-specific closed and unfilled vacancies
                $clusterClosed = [];
                $clusterUnfilled = [];
                foreach ($orderedIndices as $idx) {
                    $nodeData = $nodesAnalysis[$idx];
                    if ($nodeData['link_type'] === 'vacant') {
                        $clusterClosed[] = [
                            'jabatan' => $nodeData['target_jabatan'],
                            'unit_kerja' => $nodeData['target_unit'],
                            'filled_by' => $nodeData['nama'],
                        ];
                    }
                    $empVacKey = "{$nodeData['current_jabatan']}|{$nodeData['current_unit']}";
                    if (! isset($targetPositions[$empVacKey]) && ! in_array($nodeData['employee_nik'], $temporaryNiks, true)) {
                        $clusterUnfilled[] = [
                            'jabatan' => $nodeData['current_jabatan'],
                            'unit_kerja' => $nodeData['current_unit'],
                            'left_by' => $nodeData['nama'],
                        ];
                    }
                }

                $clusters[] = [
                    'id' => $compIdx + 1,
                    'type' => $clusterType,
                    'title' => 'Klaster #'.($compIdx + 1)." • {$clusterLabel}",
                    'type_label' => $clusterLabel,
                    'is_zero_vacancy' => ($clusterType === 'cyclic'),
                    'node_indices' => $orderedIndices,
                    'nodes' => array_map(fn ($i) => array_merge($nodesAnalysis[$i], [
                        'original_slot_index' => $i,
                        'slot_number' => $i + 1,
                    ]), $orderedIndices),
                    'loopback' => $loopback,
                    'closed_vacancies' => $clusterClosed,
                    'unfilled_vacancies' => $clusterUnfilled,
                ];
            }
        }

        // Determine chain topology
        $topology = 'invalid';
        $topologyLabel = 'Konfigurasi Belum Valid';

        if (empty($errors)) {
            $totalClusters = count($clusters);
            if ($totalClusters === 1) {
                $single = $clusters[0];
                if ($single['type'] === 'cyclic') {
                    $topology = 'cyclic';
                    $topologyLabel = 'Rantai Tertutup Tunggal (Siklis Sempurna / Net Zero Vacancy)';
                } elseif ($single['type'] === 'domino') {
                    $topology = 'domino';
                    $topologyLabel = 'Efek Domino Terbuka (1 Ditutup, 1 Ditinggalkan)';
                } else {
                    $topology = 'open';
                    $topologyLabel = 'Transfer Mandiri (Formasi Bebas)';
                }
            } else {
                if ($cycleClusterCount === $totalClusters) {
                    $topology = 'multi_cyclic';
                    $topologyLabel = "Rantai Multi-Siklis ({$cycleClusterCount} Siklus Mandiri / Net Zero Vacancy)";
                } elseif ($dominoClusterCount === $totalClusters) {
                    $topology = 'multi_domino';
                    $topologyLabel = "Rantai Multi-Domino ({$dominoClusterCount} Alur Domino Mandiri)";
                } else {
                    $parts = [];
                    if ($cycleClusterCount > 0) {
                        $parts[] = "{$cycleClusterCount} Siklis";
                    }
                    if ($dominoClusterCount > 0) {
                        $parts[] = "{$dominoClusterCount} Domino";
                    }
                    if ($openClusterCount > 0) {
                        $parts[] = "{$openClusterCount} Transfer Mandiri";
                    }
                    $topology = 'multi_mixed';
                    $topologyLabel = 'Rantai Majemuk Multi-Klaster ('.implode(', ', $parts).')';
                }
            }
        }

        return [
            'is_valid' => empty($errors),
            'topology' => $topology,
            'topology_label' => $topologyLabel,
            'errors' => $errors,
            'warnings' => $warnings,
            'clusters' => $clusters,
            'nodes_analysis' => $nodesAnalysis,
            'unfilled_vacancies' => $unfilledVacancies,
            'closed_vacancies' => $closedVacancies,
        ];
    }
}
