<?php

namespace App\Livewire\Admin;

use App\Exceptions\MutasiRuleViolationException;
use App\Exports\MutasiHistoryExport;
use App\Models\JobHistory;
use App\Services\MutasiService;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;

class MutasiHistory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $mutasiFilter = '';

    public string $statusFilter = ''; // '' = all, 'applied' = aktif/diterapkan, 'scheduled' = terjadwal

    public string $sortField = 'tmt_awal';

    public string $sortDirection = 'desc';

    public int $perPage = 15;

    public ?string $selectedBatchId = null;

    public $batchItems = null;

    public ?int $selectedJobId = null;

    public ?JobHistory $detailJob = null;

    protected $queryString = [
        'search' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'mutasiFilter' => ['except' => ''],
        'statusFilter' => ['except' => ''],
        'sortField' => ['except' => 'tmt_awal'],
        'sortDirection' => ['except' => 'desc'],
    ];

    public function toggleStatusFilter(string $status): void
    {
        $this->statusFilter = ($this->statusFilter === $status) ? '' : $status;
        $this->resetPage();
    }

    public function showDetail(int $id, MutasiService $service): void
    {
        $this->selectedJobId = $id;
        $this->detailJob = $service->getMutasiDetail($id);
    }

    public function closeDetailModal(): void
    {
        $this->selectedJobId = null;
        $this->detailJob = null;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->resetPage();
    }

    public function viewBatch(string $batchId, MutasiService $service): void
    {
        $this->selectedBatchId = $batchId;
        $this->batchItems = $service->getMutasiBatch($batchId);
    }

    public function closeBatchModal(): void
    {
        $this->selectedBatchId = null;
        $this->batchItems = null;
    }

    public function applyNow(int $jobHistoryId, MutasiService $service): void
    {
        // Activate only this mutation (and its batch partners), never other scheduled mutations
        $result = $service->applyScheduledMutation($jobHistoryId);

        if ($result['activated_count'] === 0) {
            session()->flash('mutasi_info', 'Mutasi ini sudah aktif diterapkan sebelumnya.');

            return;
        }

        $item = collect($result['items'])->firstWhere('id', $jobHistoryId);
        $msg = "Sukses! Mutasi atas nama {$item['nama']} ke {$item['jabatan']} di {$item['unit_kerja']} berhasil diaktifkan.";
        if ($result['activated_count'] > 1) {
            $msg .= " ({$result['activated_count']} pegawai dalam satu SK turut diaktifkan.)";
        }
        session()->flash('mutasi_success', $msg);

        if ($this->selectedJobId === $jobHistoryId) {
            $this->closeDetailModal();
        }
    }

    public function applyAllDue(MutasiService $service): void
    {
        $result = $service->applyEffectiveMutations();

        if ($result['activated_count'] > 0) {
            session()->flash('mutasi_success', "Sukses! Berhasil mengaktifkan {$result['activated_count']} mutasi terjadwal yang telah jatuh tempo.");
        } else {
            session()->flash('mutasi_info', 'Tidak ada mutasi terjadwal yang jatuh tempo per hari ini.');
        }
    }

    public function cancelScheduled(int $jobHistoryId, MutasiService $service): void
    {
        try {
            $result = $service->cancelScheduledMutation($jobHistoryId, 'Dibatalkan oleh Administrator SDM');
            session()->flash('mutasi_success', "Sukses! Jadwal mutasi ({$result['cancelled_count']} pegawai) berhasil dibatalkan dan formasi telah dilepaskan.");
            if ($this->selectedJobId === $jobHistoryId) {
                $this->closeDetailModal();
            }
        } catch (MutasiRuleViolationException $e) {
            // Business rule violation (already applied / nothing to cancel) — safe to show to the admin
            session()->flash('mutasi_error', "Gagal membatalkan jadwal mutasi: {$e->getMessage()}");
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingUnitFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMutasiFilter(): void
    {
        $this->resetPage();
    }

    public function updatingStatusFilter(): void
    {
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'unitFilter', 'mutasiFilter', 'statusFilter']);
        $this->sortField = 'tmt_awal';
        $this->sortDirection = 'desc';
        $this->resetPage();
    }

    public function exportAuditLog()
    {
        $isApplied = match ($this->statusFilter) {
            'applied' => true,
            'scheduled', 'due' => false,
            default => null,
        };
        $onlyDue = ($this->statusFilter === 'due');

        $filters = [
            'search' => $this->search ?: null,
            'unitKerja' => $this->unitFilter ?: null,
            'jenisMutasi' => $this->mutasiFilter ?: null,
            'isApplied' => $isApplied,
            'onlyDue' => $onlyDue,
            'sortBy' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ];

        $filename = 'Audit_Log_Mutasi_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new MutasiHistoryExport($filters, auth()->user()), $filename);
    }

    public function render(MutasiService $service)
    {
        $isApplied = match ($this->statusFilter) {
            'applied' => true,
            'scheduled', 'due' => false,
            default => null,
        };
        $onlyDue = ($this->statusFilter === 'due');

        $history = $service->getMutasiHistory(
            search: $this->search ?: null,
            unitKerja: $this->unitFilter ?: null,
            jenisMutasi: $this->mutasiFilter ?: null,
            isApplied: $isApplied,
            onlyDue: $onlyDue,
            perPage: $this->perPage,
            sortBy: $this->sortField,
            sortDirection: $this->sortDirection,
        );

        $units = $service->getKnownUnits();
        $mutasiTypes = JobHistory::MUTASI_TYPES;
        $kpis = $service->getMutasiHistoryKpis();

        return view('livewire.admin.mutasi-history', [
            'history' => $history,
            'units' => $units,
            'mutasiTypes' => $mutasiTypes,
            'totalCount' => $kpis['total'],
            'activeCount' => $kpis['active'],
            'scheduledCount' => $kpis['scheduled'],
            'dueCount' => $kpis['due'],
        ]);
    }
}
