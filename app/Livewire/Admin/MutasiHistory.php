<?php

namespace App\Livewire\Admin;

use App\Exceptions\MutasiRuleViolationException;
use App\Exports\MutasiHistoryExport;
use App\Livewire\Concerns\WithDataTable;
use App\Models\JobHistory;
use App\Models\User;
use App\Services\MutasiService;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Mutation history (Riwayat Mutasi): the audit trail of every mutation, TMT schedule activation and
 * the compliance log export.
 *
 * Runs on the shared data table layer (WithDataTable), which validates search, sort, filters and
 * page size; the meaning of each status and record source is defined only by the JobHistory scopes
 * (DOMAIN.md), never by this component.
 */
class MutasiHistory extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $unitFilter = '';

    #[Url(except: '')]
    public string $mutasiFilter = '';

    /** '' = all; otherwise a key of statusOptions(): 'active', 'applied', 'scheduled' or 'due'. */
    #[Url(except: '')]
    public string $statusFilter = '';

    /** '' (default) = official mutations only; 'semua' = every record, or one key of JobHistory::SUMBER_LABELS. */
    #[Url(except: '')]
    public string $sumberFilter = '';

    public ?string $selectedBatchId = null;

    public $batchItems = null;

    public ?int $selectedJobId = null;

    public ?JobHistory $detailJob = null;

    /**
     * Admin-only screen that changes formation data and shows personal data: refuse non-admins on every
     * request, because route middleware is not re-applied to Livewire update requests.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /**
     * Known units for the unit filter; computed once per request because filters() is built several times.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function units(): array
    {
        return app(MutasiService::class)->getKnownUnits()->all();
    }

    protected function baseQuery(): Builder
    {
        // The compliance export maps employee and creator per row: keep them eager loaded.
        return JobHistory::with(['employee', 'creator'])
            // The official-only default is enforced here, not left to the trait's normalisation.
            ->when($this->sumber() === 'resmi', fn (Builder $query) => $query->bySumber('resmi'));
    }

    protected function columns(): array
    {
        return [
            Column::make('employee_nik', 'Karyawan')
                ->sortable()
                ->searchable(fn (Builder $query, string $term) => $query->whereHas(
                    'employee',
                    fn (Builder $employee) => QueryPipeline::whereLikeAny($employee, ['nama', 'nik'], $term)
                ))
                ->width('w-[18%]'),
            Column::make('jabatan', 'Jabatan & Unit Kerja')
                ->sortable()
                ->searchable(fn (Builder $query, string $term) => QueryPipeline::whereLikeAny($query, ['jabatan', 'unit_kerja'], $term))
                ->width('w-[24%]'),
            Column::make('jenis_mutasi', 'Jenis & Regulasi')->sortable()->align('center')->width('w-[12%]'),
            Column::make('tmt_awal', 'TMT Efektif')->sortable()->width('w-[12%]'),
            Column::make('status', 'Status')->align('center')->width('w-[12%]'),
            Column::make('nomor_sk', 'Dasar SK')->sortable()->width('w-[14%]'),
            Column::make('aksi', 'Aksi')->align('center')->width('w-[8%]'),
        ];
    }

    protected function filters(): array
    {
        $units = $this->units;
        $sumberOptions = ['semua' => 'Semua Catatan'];

        foreach (JobHistory::SUMBER_LABELS as $key => $label) {
            if ($key !== JobHistory::SUMBER_MUTASI) {
                $sumberOptions[$key] = $label;
            }
        }

        return [
            Filter::select('unitFilter', 'Unit Kerja', array_combine($units, $units))
                ->icon('fas fa-building')
                ->allLabel('Semua Unit Kerja')
                ->apply(fn (Builder $query, string $value) => $query->where('unit_kerja', $value)),
            Filter::select('mutasiFilter', 'Jenis Mutasi', array_combine(
                JobHistory::MUTASI_TYPES,
                array_map(fn (string $type) => JobHistory::labelFor($type), JobHistory::MUTASI_TYPES)
            ))
                ->icon('fas fa-tag')
                ->apply(fn (Builder $query, string $value) => $query->where('jenis_mutasi', $value)),
            Filter::select('statusFilter', 'Status', $this->statusOptions())
                ->icon('fas fa-flag')
                ->apply(fn (Builder $query, string $value) => $query->byStatus($value)),
            Filter::select('sumberFilter', 'Sumber Catatan', $sumberOptions)
                ->icon('fas fa-layer-group')
                ->allLabel('Mutasi Resmi')
                ->apply(fn (Builder $query, string $value) => $query->bySumber($value)),
        ];
    }

    /**
     * Status filter options, keyed by the JobHistory::byStatus values. `active` matches the Aktif Menjabat
     * KPI card; `applied` also lists finished rows.
     *
     * @return array<string, string>
     */
    private function statusOptions(): array
    {
        return [
            'active' => 'Aktif Menjabat',
            'scheduled' => 'Terjadwal',
            'due' => 'Siap Diaktifkan',
            'applied' => 'Sudah Diterapkan',
        ];
    }

    protected function defaultSort(): array
    {
        return ['tmt_awal', 'desc'];
    }

    protected function defaultPerPage(): int
    {
        return 15;
    }

    protected function perPageOptions(): array
    {
        return [10, 15, 25, 50, 100];
    }

    protected function tableLabel(): string
    {
        return 'Riwayat Mutasi';
    }

    protected function exportName(): ?string
    {
        return 'riwayat-mutasi';
    }

    protected function exportFileName(): string
    {
        return 'Audit_Log_Mutasi_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx';
    }

    /** The compliance workbook (Inspektorat/BPK format) over exactly the rows on screen. */
    protected function buildExport(Builder $query, ?User $user): object
    {
        return new MutasiHistoryExport(['sumber' => $this->sumber()], $user, $query);
    }

    /**
     * Source scope for the base query, the KPI counts and the export metadata. Fails closed: the empty
     * default, SUMBER_MUTASI itself and any unknown value all mean official mutations ('resmi').
     */
    private function sumber(): string
    {
        $value = $this->sumberFilter;

        if ($value === 'semua' || ($value !== JobHistory::SUMBER_MUTASI && array_key_exists($value, JobHistory::SUMBER_LABELS))) {
            return $value;
        }

        return 'resmi';
    }

    /** KPI-card toggle; values outside the status filter options are ignored (server calls bypass the trait's normalisation). */
    public function toggleStatusFilter(string $status): void
    {
        if ($status !== '' && ! array_key_exists($status, $this->statusOptions())) {
            return;
        }

        $this->statusFilter = ($this->statusFilter === $status) ? '' : $status;
        $this->resetPage($this->paginationName());
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

    public function render(MutasiService $service): View
    {
        $kpis = $service->getMutasiHistoryKpis($this->sumber());

        return view('livewire.admin.mutasi-history', [
            'totalCount' => $kpis['total'],
            'activeCount' => $kpis['active'],
            'scheduledCount' => $kpis['scheduled'],
            'dueCount' => $kpis['due'],
        ]);
    }
}
