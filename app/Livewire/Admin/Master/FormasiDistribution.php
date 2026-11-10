<?php

namespace App\Livewire\Admin\Master;

use App\Exports\FormasiExport;
use App\Models\Position;
use App\Models\Unit;
use App\Services\JobVacancyService;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Tab "Sebaran Formasi" (DOMAIN.md §9.3): gambaran lintas unit seluruh formasi jabatan, terisi maupun kosong,
 * per rumpun jabatan (bisa dibuka per unit) atau per unit. Seluruh perhitungan berada di JobVacancyService; komponen
 * ini hanya memvalidasi filter, mengurutkan, memaginasi, membuka rumpun, dan mengekspor. Berbasis koleksi, jadi tidak
 * memakai WithDataTable, tetapi memberi komponen tabel bersama bentuk method yang sama.
 */
class FormasiDistribution extends Component
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [10, 25, 50];

    public const VIEW_JABATAN = 'jabatan';

    public const VIEW_UNIT = 'unit';

    private const SEARCH_MAX_LENGTH = 100;

    /** Kolom yang dapat diurutkan pada masing-masing tampilan. */
    private const SORTABLE = [
        self::VIEW_JABATAN => ['family', 'unit_count', 'kuota', 'filled', 'vacant_slots', 'fill_rate'],
        self::VIEW_UNIT => ['unit_kerja', 'jabatan', 'kuota', 'filled', 'vacant_slots', 'days_vacant'],
    ];

    private const SEVERITY_LABELS = [
        'red' => 'Kritis (> 90 hari)',
        'yellow' => 'Perhatian (30–90 hari)',
        'green' => 'Baru kosong (< 30 hari)',
        'unknown' => 'Belum pernah terisi',
    ];

    #[Url(as: 'tampilan', except: self::VIEW_JABATAN)]
    public string $view = self::VIEW_JABATAN;

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'unit', except: '')]
    public string $unitFilter = '';

    #[Url(as: 'wilayah', except: '')]
    public string $wilayahFilter = '';

    #[Url(as: 'rm', except: '')]
    public string $rmFilter = '';

    #[Url(as: 'bidang', except: '')]
    public string $bidangFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'durasi', except: '')]
    public string $severityFilter = '';

    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'direction', except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(as: 'per_page', except: 25)]
    public int $perPage = 25;

    /** Rumpun jabatan yang rinciannya sedang dibuka (hanya tampilan per jabatan). */
    public ?string $expanded = null;

    /** @var ?array<string, string> */
    private ?array $unitOptions = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->normaliseAll();
    }

    /** Pencarian, filter, atau halaman berubah: kembali ke halaman pertama dan tutup rincian rumpun. */
    public function updated(): void
    {
        $this->normaliseAll();
        $this->expanded = null;
        $this->resetPage();
    }

    public function setView(string $view): void
    {
        if (! in_array($view, [self::VIEW_JABATAN, self::VIEW_UNIT], true)) {
            return;
        }

        $this->view = $view;
        $this->expanded = null;
        $this->normaliseAll();
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, self::SORTABLE[$this->view], true)) {
            return;
        }

        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }

        $this->resetPage();
    }

    /** Buka atau tutup rincian satu rumpun; hanya rumpun yang sedang tampil pada hasil yang diterima. */
    public function toggleFamily(string $family): void
    {
        $this->authorizeAdmin();

        $listed = $this->distribution(app(JobVacancyService::class))->contains('family', $family);

        $this->expanded = $listed && $this->expanded !== $family ? $family : null;
    }

    /** Kartu KPI "Formasi Kosong" berfungsi sebagai pintasan filter status. */
    public function toggleKosongFilter(): void
    {
        $this->statusFilter = $this->statusFilter === JobVacancyService::STATUS_ANY_VACANT ? '' : JobVacancyService::STATUS_ANY_VACANT;
        $this->expanded = null;
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'unitFilter', 'wilayahFilter', 'rmFilter', 'bidangFilter', 'statusFilter', 'severityFilter', 'expanded']);
        $this->resetPage();
    }

    /** Unduh Excel sesuai tampilan dan filter yang aktif (seluruh hasil, bukan hanya halaman ini). Tanpa nama karyawan (UU PDP). */
    public function export(JobVacancyService $service): BinaryFileResponse
    {
        $this->authorizeAdmin();
        $this->normaliseAll();

        if ($this->view === self::VIEW_JABATAN) {
            $rows = $this->sorted($this->distribution($service))->map(fn (object $row, int $i): array => [
                $i + 1,
                $row->family,
                $row->rm_levels !== [] ? implode(', ', $row->rm_levels) : '-',
                $row->unit_count,
                $row->kuota,
                $row->filled,
                $row->vacant_slots,
                $row->fill_rate,
            ]);
            $export = new FormasiExport('Sebaran Jabatan', ['NO', 'JABATAN', 'RM BAND', 'JUMLAH UNIT', 'KUOTA', 'TERISI', 'KOSONG', 'KETERISIAN (%)'], $rows, auth()->user());
        } else {
            $rows = $this->sorted($this->seats($service))->map(fn (object $row, int $i): array => [
                $i + 1,
                $row->unit_kerja,
                $row->wilayah,
                $row->jabatan,
                $row->rm_level ?? '-',
                $row->bidang ?? '-',
                $row->kuota,
                $row->filled,
                $row->vacant_slots,
                $row->status_label,
                $row->vacant_slots <= 0 ? '-' : ($row->vacant_since_formatted ?? 'Belum pernah terisi'),
            ]);
            $export = new FormasiExport('Sebaran Unit', ['NO', 'UNIT KERJA', 'WILAYAH', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'KOSONG', 'STATUS FORMASI', 'KOSONG SEJAK'], $rows->values(), auth()->user());
        }

        $name = 'Sebaran_Formasi_'.($this->view === self::VIEW_JABATAN ? 'Jabatan' : 'Unit').'_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx';

        return Excel::download($export, $name);
    }

    public function render(JobVacancyService $service): View
    {
        $this->authorizeAdmin();
        // Re-validate on every render: a stale or tampered value must never reach the service.
        $this->normaliseAll();

        $rows = $this->sorted($this->view === self::VIEW_JABATAN ? $this->distribution($service) : $this->seats($service));

        $page = max(1, min($this->getPage(), (int) ceil($rows->count() / $this->perPage)));
        $paginator = new LengthAwarePaginator($rows->forPage($page, $this->perPage)->values(), $rows->count(), $this->perPage, $page);

        $filterValues = $this->activeFilterValues($service);

        return view('livewire.admin.master.formasi-distribution', [
            'rows' => $paginator,
            'stats' => $service->getFormasiStatistics(),
            'filters' => $this->filters($service),
            'filterValues' => $filterValues,
            'hasActiveFilters' => $this->search !== '' || $filterValues !== [],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
            'sortableFields' => self::SORTABLE[$this->view],
        ]);
    }

    /** @return Collection<int, object> */
    private function distribution(JobVacancyService $service): Collection
    {
        return $service->getJabatanDistribution(...$this->serviceFilters());
    }

    /** @return Collection<int, object> */
    private function seats(JobVacancyService $service): Collection
    {
        return $service->getFormasiSeats(...$this->serviceFilters());
    }

    /** @return array<string, ?string> */
    private function serviceFilters(): array
    {
        $orNull = fn (string $value): ?string => $value !== '' ? $value : null;

        return [
            'search' => $orNull($this->search),
            'unitKerja' => $orNull($this->unitFilter),
            'status' => $orNull($this->statusFilter),
            'wilayah' => $orNull($this->wilayahFilter),
            'rmLevel' => $orNull($this->rmFilter),
            'bidang' => $orNull($this->bidangFilter),
            'severity' => $orNull($this->severityFilter),
        ];
    }

    /** @param  Collection<int, object>  $rows */
    private function sorted(Collection $rows): Collection
    {
        if ($this->sortField === '') {
            return $rows;
        }

        $field = $this->sortField;

        return $rows->sortBy(function (object $row) use ($field) {
            $value = $row->{$field} ?? -1;

            return is_string($value) ? mb_strtolower($value) : $value;
        }, SORT_REGULAR, $this->sortDirection === 'desc')->values();
    }

    /** @return array<int, Filter> */
    protected function filters(?JobVacancyService $service = null): array
    {
        $service ??= app(JobVacancyService::class);

        return [
            Filter::select('unitFilter', 'Unit Kerja', $this->unitOptions($service))->icon('fas fa-building'),
            Filter::select('wilayahFilter', 'Wilayah', array_combine(Unit::WILAYAH_LIST, Unit::WILAYAH_LIST))->icon('fas fa-location-dot'),
            Filter::select('rmFilter', 'RM Band', array_combine(array_keys(Position::RM_LEVEL_LIST), array_keys(Position::RM_LEVEL_LIST)))->icon('fas fa-layer-group'),
            Filter::select('bidangFilter', 'Bidang', Position::BIDANG_LIST)->icon('fas fa-sitemap'),
            Filter::select('statusFilter', 'Status Formasi', [JobVacancyService::STATUS_ANY_VACANT => 'Ada kekosongan'] + JobVacancyService::SEAT_STATUS_LABELS)->icon('fas fa-toggle-on'),
            Filter::select('severityFilter', 'Lama Kosong', self::SEVERITY_LABELS)->icon('fas fa-clock'),
        ];
    }

    /** @return array<string, string> [filter property => value] for filters that are set and allowed. */
    protected function activeFilterValues(?JobVacancyService $service = null): array
    {
        $values = [];

        foreach ($this->filters($service) as $filter) {
            $value = $this->{$filter->property};

            if ($value !== '' && $filter->allows($value)) {
                $values[$filter->property] = $value;
            }
        }

        return $values;
    }

    /** @return array<string, string> [unit => unit] of every active unit that has a formasi. */
    private function unitOptions(JobVacancyService $service): array
    {
        // filters() runs several times per request; resolve the options once.
        if ($this->unitOptions === null) {
            $units = $service->getFormasiSeats()->pluck('unit_kerja')->unique()->sort()->values()->all();
            $this->unitOptions = array_combine($units, $units) ?: [];
        }

        return $this->unitOptions;
    }

    private function normaliseAll(): void
    {
        $this->search = mb_substr(trim($this->search), 0, self::SEARCH_MAX_LENGTH);

        if (! in_array($this->view, [self::VIEW_JABATAN, self::VIEW_UNIT], true)) {
            $this->view = self::VIEW_JABATAN;
        }

        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 25;
        }

        if (! in_array($this->sortField, self::SORTABLE[$this->view], true)) {
            $this->sortField = '';
        }

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'asc';
        }

        foreach ($this->filters() as $filter) {
            if (! $filter->allows($this->{$filter->property})) {
                $this->{$filter->property} = '';
            }
        }
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }
}
