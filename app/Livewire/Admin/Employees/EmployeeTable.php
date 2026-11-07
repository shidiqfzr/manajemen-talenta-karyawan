<?php

namespace App\Livewire\Admin\Employees;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Employee;
use App\Models\JobHistory;
use App\Models\Position;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Direktori Pegawai (admin employees index) on the shared data table layer (WithDataTable).
 *
 * Every filter keeps its URL key from the former GET form, so old bookmarks open with the same
 * filters, sort and page. The KPI cards (rendered here so they follow the live state) drive `level`
 * and the governance strip drives `status_khusus` through `$set`, which the shared layer validates
 * like any filter value; the toolbar shows them only as removable chips.
 * Row actions stay plain links and the CSRF-protected DELETE form handled by EmployeeController;
 * row selection, bulk delete and the export button still post to their existing routes.
 */
class EmployeeTable extends Component
{
    use WithDataTable;

    /** Month number => Indonesian month name, as the former directory offered it. */
    public const MONTHS = [
        '01' => 'Januari', '02' => 'Februari', '03' => 'Maret',
        '04' => 'April',   '05' => 'Mei',      '06' => 'Juni',
        '07' => 'Juli',    '08' => 'Agustus',  '09' => 'September',
        '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
    ];

    /** Governance strip value => label. */
    public const STATUS_KHUSUS = [
        'rangkap' => 'Tugas Rangkap',
        'sanksi' => 'Masa Sanksi (Diperban)',
        'overflow' => 'Jabatan Overflow (Advisor)',
    ];

    #[Url(except: '')]
    public string $jabatan = '';

    #[Url(except: '')]
    public string $level = '';

    #[Url(as: 'unit_kerja', except: '')]
    public string $unitKerja = '';

    #[Url(as: 'rm_level', except: '')]
    public string $rmLevel = '';

    #[Url(except: '')]
    public string $golongan = '';

    #[Url(as: 'status_penugasan', except: '')]
    public string $statusPenugasan = '';

    #[Url(as: 'status_khusus', except: '')]
    public string $statusKhusus = '';

    #[Url(as: 'tahun_masuk', except: '')]
    public string $tahunMasuk = '';

    #[Url(as: 'bulan_masuk', except: '')]
    public string $bulanMasuk = '';

    /**
     * Page-level KPI metrics computed by EmployeeController::index (total, units, karpim, karpel,
     * rangkap, sanksi, overflow, expired). Empty: the cards and the governance strip are not rendered.
     *
     * @var array<string, int>
     */
    #[Locked]
    public array $kpi = [];

    /**
     * Admin-only screen: route middleware is not re-applied to Livewire update requests, so refuse
     * non-admins on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    /** Runs before the shared validation: older links may carry an unpadded month (`bulan_masuk=3`). */
    public function mount(): void
    {
        $this->bulanMasuk = $this->padMonth($this->bulanMasuk);
    }

    /** "Total Pegawai" card: clears the filters the KPI cards and governance strip drive, keeps search and panel filters. */
    public function clearKpiFilters(): void
    {
        $this->level = '';
        $this->statusKhusus = '';
        $this->statusPenugasan = '';

        $this->resetPage($this->paginationName());
        $this->clearSelection();
    }

    /**
     * No-JS fallback link of a KPI card or strip chip: the current table state with the given changes
     * (URL key => value, '' removes the key).
     *
     * @param  array<string, string>  $changes
     */
    public function kpiUrl(array $changes): string
    {
        $query = array_filter(array_merge($this->exportQuery(), $changes), fn ($value) => $value !== '');

        return route('admin.employees.index', $query);
    }

    /** @return array<string, string> */
    #[Computed]
    public function jabatanOptions(): array
    {
        return $this->distinctOptions('jabatan');
    }

    /** @return array<string, string> */
    #[Computed]
    public function levelOptions(): array
    {
        return $this->distinctOptions('level');
    }

    /** @return array<string, string> */
    #[Computed]
    public function unitOptions(): array
    {
        return $this->distinctOptions('unit_kerja');
    }

    /** @return array<string, string> */
    #[Computed]
    public function golonganOptions(): array
    {
        return $this->distinctOptions('golongan');
    }

    /**
     * Hire years present in the data, newest first (fast substring extraction).
     *
     * @return array<string, string>
     */
    #[Computed]
    public function yearOptions(): array
    {
        return Employee::whereNotNull('tmt_bekerja')
            ->pluck('tmt_bekerja')
            ->map(fn ($date) => $date ? substr((string) $date, 0, 4) : null)
            ->filter()
            ->unique()
            ->sortDesc()
            ->mapWithKeys(fn ($year) => [(string) $year => 'Tahun '.$year])
            ->all();
    }

    /**
     * `sementara` (any concurrent assignment), `definitif` (none) or one concurrent assignment status.
     *
     * @return array<string, string>
     */
    #[Computed]
    public function statusPenugasanOptions(): array
    {
        $statuses = JobHistory::query()
            ->where('is_applied', true)
            ->whereNull('tmt_akhir')
            ->whereNotNull('status_penugasan')
            ->where('status_penugasan', '!=', '')
            ->where('status_penugasan', '!=', 'Definitif')
            ->distinct()
            ->orderBy('status_penugasan')
            ->pluck('status_penugasan')
            ->mapWithKeys(fn ($status) => [(string) $status => (string) $status])
            ->all();

        return ['sementara' => 'Penugasan Sementara', 'definitif' => 'Definitif'] + $statuses;
    }

    /**
     * Query keys and values of the current search, sort and filters for the existing GET export route.
     *
     * @return array<string, string>
     */
    public function exportQuery(): array
    {
        $keys = [
            'jabatan' => 'jabatan',
            'level' => 'level',
            'unitKerja' => 'unit_kerja',
            'rmLevel' => 'rm_level',
            'golongan' => 'golongan',
            'statusPenugasan' => 'status_penugasan',
            'statusKhusus' => 'status_khusus',
            'tahunMasuk' => 'tahun_masuk',
            'bulanMasuk' => 'bulan_masuk',
        ];

        $query = array_filter(['search' => $this->search], fn ($value) => $value !== '');

        foreach ($this->activeFilterValues() as $property => $value) {
            $query[$keys[$property]] = (string) $value;
        }

        return $query + ['sort' => $this->sortField, 'direction' => $this->sortDirection];
    }

    protected function baseQuery(): Builder
    {
        return Employee::query()->with('concurrentJobs');
    }

    protected function rowKey(): string
    {
        return 'nik';
    }

    protected function columns(): array
    {
        return [
            // Keyed `nama` so the sort URL key stays `sort=nama`; the search runs the Omnisearch scope (NIK, nama, jabatan, unit kerja).
            Column::make('nama', 'Pegawai')
                ->sortable()
                ->searchable(fn (Builder $q, string $term) => $q->search($term))
                ->width('min-w-[200px]'),
            Column::make('jabatan', 'Jabatan & Unit Kerja')->sortable()->width('min-w-[260px]'),
            Column::make('level', 'Strata')->sortable()->align('center')->width('w-28'),
            Column::make('rm_level', 'Eselon')->sortable()->align('center')->width('w-28'),
            Column::make('golongan', 'Golongan')->sortable()->align('center')->width('w-28'),
            Column::make('aksi', 'Aksi')->align('right')->width('w-16'),
        ];
    }

    /** The apply() closures are the former EmployeeController::index conditions, unchanged. */
    protected function filters(): array
    {
        return [
            Filter::select('unitKerja', 'Unit Kerja', $this->unitOptions)
                ->icon('fas fa-building')
                ->allLabel('Semua Unit Kerja')
                ->apply(fn (Builder $query, string $value) => $query->where('unit_kerja', $value)),
            Filter::select('jabatan', 'Jabatan', $this->jabatanOptions)
                ->icon('fas fa-briefcase')
                ->allLabel('Semua Jabatan')
                ->apply(fn (Builder $query, string $value) => $query->where('jabatan', $value)),
            Filter::select('rmLevel', 'Eselon / RM Band', Position::RM_LEVEL_LIST)
                ->icon('fas fa-layer-group')
                ->allLabel('Semua Eselon / RM Band')
                ->apply(fn (Builder $query, string $value) => $query->where('rm_level', $value)),
            Filter::select('tahunMasuk', 'Tahun Masuk (TMT)', $this->yearOptions)
                ->icon('fas fa-calendar-alt')
                ->allLabel('Semua Angkatan')
                // Period filter: Tahun Masuk (TMT Bekerja)
                ->apply(fn (Builder $query, string $value) => $query->whereYear('tmt_bekerja', $value)),
            Filter::select('bulanMasuk', 'Bulan Masuk (TMT)', self::MONTHS)
                ->icon('fas fa-calendar-day')
                ->allLabel('Semua Bulan')
                // Period filter: Bulan Masuk (TMT Bekerja)
                ->apply(fn (Builder $query, string $value) => $query->whereMonth('tmt_bekerja', $value)),
            Filter::select('golongan', 'Golongan', $this->golonganOptions)
                ->icon('fas fa-signal')
                ->allLabel('Semua Golongan')
                ->apply(fn (Builder $query, string $value) => $query->where('golongan', $value)),
            // Driven by the KPI cards above the table.
            Filter::select('level', 'Strata', $this->levelOptions)
                ->withoutControl()
                ->apply(fn (Builder $query, string $value) => $query->where('level', $value)),
            // Status Khusus / Tata Kelola (Sanksi, Overflow, Rangkap), driven by the governance strip above the table.
            Filter::select('statusKhusus', 'Tata Kelola', self::STATUS_KHUSUS)
                ->withoutControl()
                ->apply(function (Builder $query, string $khusus) {
                    if ($khusus === 'sanksi') {
                        $query->sanctionAll();
                    } elseif ($khusus === 'overflow') {
                        $query->overflow();
                    } elseif ($khusus === 'rangkap') {
                        $query->whereHas('concurrentJobs');
                    }
                }),
            // Status Penugasan (Definitif vs Plt/Pjs/Diperbantukan); reached through links only, as before.
            Filter::select('statusPenugasan', 'Status Penugasan', $this->statusPenugasanOptions)
                ->withoutControl()
                ->apply(function (Builder $query, string $status) {
                    if ($status === 'sementara') {
                        $query->whereHas('concurrentJobs');
                    } elseif ($status === 'definitif') {
                        $query->whereDoesntHave('concurrentJobs');
                    } else {
                        $query->whereHas('concurrentJobs', function ($q) use ($status) {
                            $q->where('status_penugasan', $status);
                        });
                    }
                }),
        ];
    }

    protected function defaultSort(): array
    {
        return ['nama', 'asc'];
    }

    protected function tableLabel(): string
    {
        return 'Direktori Pegawai';
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 20, 50, 100];
    }

    protected function defaultPerPage(): int
    {
        return 20;
    }

    public function render(): View
    {
        return view('livewire.admin.employees.employee-table');
    }

    /** Distinct non-empty values of an employees column, sorted, as [value => value]. */
    private function distinctOptions(string $column): array
    {
        return Employee::select($column)
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->mapWithKeys(fn ($value) => [(string) $value => (string) $value])
            ->all();
    }

    private function padMonth(string $value): string
    {
        return preg_match('/^[1-9]$/D', $value) === 1 ? '0'.$value : $value;
    }
}
