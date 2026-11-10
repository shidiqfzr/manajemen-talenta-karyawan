<?php

namespace App\Livewire\Admin\Master;

use App\Exports\FormasiExport;
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
 * Tab "Formasi Lowong" (DOMAIN.md §9.3): daftar lintas unit seluruh formasi jabatan yang belum terisi pejabat definitif.
 * Seluruh perhitungan berada di JobVacancyService; komponen ini hanya memvalidasi filter, memaginasi, dan mengekspor.
 * Berbasis koleksi, jadi tidak memakai WithDataTable, tetapi memberi komponen tabel bersama bentuk method yang sama.
 */
class FormasiVacancyTable extends Component
{
    use WithPagination;

    public const PER_PAGE_OPTIONS = [10, 25, 50];

    private const SEARCH_MAX_LENGTH = 100;

    private const SEVERITY_LABELS = [
        'red' => 'Kritis (> 90 hari)',
        'yellow' => 'Perhatian (30–90 hari)',
        'green' => 'Baru kosong (< 30 hari)',
        'unknown' => 'Belum pernah terisi',
    ];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'unit', except: '')]
    public string $unitFilter = '';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'durasi', except: '')]
    public string $severityFilter = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** @var ?array<string, string> */
    private ?array $unitOptions = null;

    public function mount(): void
    {
        $this->authorizeAdmin();
        $this->normaliseAll();
    }

    public function updated(): void
    {
        $this->normaliseAll();
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', 'unitFilter', 'statusFilter', 'severityFilter']);
        $this->resetPage();
    }

    /** Unduh Excel sesuai filter yang aktif (seluruh hasil, bukan hanya halaman ini). Tanpa nama karyawan (UU PDP). */
    public function exportVacancies(JobVacancyService $service): BinaryFileResponse
    {
        $this->authorizeAdmin();
        $this->normaliseAll();

        $rows = $this->vacancies($service)->values()->map(fn (object $row, int $i): array => [
            $i + 1,
            $row->unit_kerja,
            $row->jabatan,
            $row->rm_level ?? '-',
            $row->bidang ?? '-',
            $row->kuota,
            $row->filled,
            $row->vacant_slots,
            $row->status_label,
            $row->vacant_since_formatted ?? 'Belum pernah terisi',
            $row->is_reserved ? 'Terjadwal (SK)' : '-',
        ]);

        $export = new FormasiExport(
            'Formasi Lowong',
            ['NO', 'UNIT KERJA', 'JABATAN', 'RM BAND', 'BIDANG', 'KUOTA', 'TERISI', 'LOWONG', 'STATUS FORMASI', 'KOSONG SEJAK', 'PEMESANAN'],
            $rows,
            auth()->user()
        );

        return Excel::download($export, 'Formasi_Lowong_PTPN4_Reg5_'.now()->format('Ymd_His').'.xlsx');
    }

    public function render(JobVacancyService $service): View
    {
        $this->authorizeAdmin();
        // Re-validate on every render: a stale or tampered value must never reach the service.
        $this->normaliseAll();

        $stats = $service->getFormasiStatistics();
        $rows = $this->vacancies($service);

        $perPage = $this->perPage;
        $page = max(1, min($this->getPage(), (int) ceil($rows->count() / $perPage)));
        $paginator = new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page);

        return view('livewire.admin.master.formasi-vacancy-table', [
            'vacancies' => $paginator,
            'stats' => $stats,
            'filters' => $this->filters($service),
            'filterValues' => $this->activeFilterValues($service),
            'hasActiveFilters' => $this->search !== '' || $this->activeFilterValues($service) !== [],
            'perPageOptions' => self::PER_PAGE_OPTIONS,
        ]);
    }

    /** @return Collection<int, object> */
    private function vacancies(JobVacancyService $service): Collection
    {
        return $service
            ->getFormasiVacancies(
                search: $this->search !== '' ? $this->search : null,
                unitKerja: $this->unitFilter !== '' ? $this->unitFilter : null,
                status: $this->statusFilter !== '' ? $this->statusFilter : null,
            )
            ->when($this->severityFilter !== '', fn (Collection $rows) => $rows->where('severity', $this->severityFilter))
            ->values();
    }

    /** @return array<int, Filter> */
    protected function filters(?JobVacancyService $service = null): array
    {
        $service ??= app(JobVacancyService::class);

        return [
            Filter::select('unitFilter', 'Unit Kerja', $this->unitOptions($service))->icon('fas fa-building'),
            Filter::select('statusFilter', 'Status Formasi', array_intersect_key(
                JobVacancyService::SEAT_STATUS_LABELS,
                array_flip(JobVacancyService::VACANT_STATUSES)
            ))->icon('fas fa-toggle-on'),
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

    /** @return array<string, string> [unit => unit] of the active units that have a vacancy. */
    private function unitOptions(JobVacancyService $service): array
    {
        // filters() runs several times per request; resolve the options once.
        if ($this->unitOptions === null) {
            $units = $service->getFormasiVacancies()->pluck('unit_kerja')->unique()->sort()->values()->all();
            $this->unitOptions = array_combine($units, $units) ?: [];
        }

        return $this->unitOptions;
    }

    private function normaliseAll(): void
    {
        $this->search = mb_substr(trim($this->search), 0, self::SEARCH_MAX_LENGTH);

        if (! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = 10;
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
