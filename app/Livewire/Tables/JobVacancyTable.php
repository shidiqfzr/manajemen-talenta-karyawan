<?php

namespace App\Livewire\Tables;

use App\Services\JobVacancyService;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Collection-backed vacancy list. The data comes from JobVacancyService (computed severity), so it
 * cannot use the Eloquent-only WithDataTable trait; it keeps in-memory pagination but feeds the
 * shared presentational table components with the same method shapes (filters(), activeFilterValues(),
 * hasActiveFilters()). Every client-writable value is re-validated before it reaches the service.
 */
class JobVacancyTable extends Component
{
    use WithPagination;

    private const SEARCH_MAX_LENGTH = 100;

    /** Old links used the property names as query keys. */
    private const LEGACY_QUERY_KEYS = ['selectedUnit', 'severityFilter'];

    #[Url(except: '')]
    public string $search = '';

    #[Url(as: 'unit', except: '')]
    public string $selectedUnit = '';

    #[Url(as: 'severity', except: '')]
    public string $severityFilter = '';

    /** Empty keeps the service order (longest vacant first, never-filled last). */
    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'direction', except: 'asc')]
    public string $sortDirection = 'asc';

    public int $perPage = 25;

    /** @var ?array<string, string> */
    private ?array $unitOptions = null;

    /** Seluruh formasi kosong, dimuat sekali per permintaan lalu disaring di memori. */
    private ?Collection $allVacancies = null;

    /** The new query keys win; a legacy key only fills a property the new key left empty. */
    public function mount(): void
    {
        foreach (self::LEGACY_QUERY_KEYS as $property) {
            $legacy = request()->query($property);

            if ($this->{$property} === '' && is_string($legacy)) {
                $this->{$property} = $legacy;
            }
        }

        $this->search = $this->normaliseSearch($this->search);
        $this->normalisePerPage();
        $this->normaliseSort();

        foreach ($this->filters() as $filter) {
            $this->normaliseFilter($filter);
        }
    }

    public function updated(string $property): void
    {
        if ($property === 'search') {
            $this->search = $this->normaliseSearch($this->search);
        } elseif ($property === 'perPage') {
            $this->normalisePerPage();
        } elseif ($property === 'sortField' || $property === 'sortDirection') {
            $this->normaliseSort();
        } else {
            foreach ($this->filters() as $filter) {
                if ($filter->property === $property) {
                    $this->normaliseFilter($filter);
                }
            }
        }

        $this->resetPage();
    }

    /** @return array<int, Filter> */
    protected function filters(): array
    {
        return [
            Filter::select('selectedUnit', 'Unit Kerja', $this->unitOptions())->icon('fas fa-building'),
            // The KPI cards drive urgency, so the toolbar shows only its chip.
            Filter::select('severityFilter', 'Urgensi', [
                'red' => 'Kritis (> 90 hari)',
                'yellow' => 'Perhatian (30 - 90 hari)',
                'green' => 'Baru (< 30 hari)',
                'unknown' => 'Belum Pernah Terisi',
            ])->withoutControl(),
        ];
    }

    /** Sortable column => property of the vacancy row. */
    private const SORTABLE = [
        'unit_kerja' => 'unit_kerja',
        'jabatan' => 'jabatan',
        'pejabat' => 'last_occupant_name',
        'lama_kosong' => 'days_vacant',
    ];

    /** Same toggle as the Eloquent tables: first click ascending, second descending. */
    public function sortBy(string $field): void
    {
        if (! array_key_exists($field, self::SORTABLE)) {
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

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return array_map('intval', config('hris.pagination.options'));
    }

    /** @return array<string, string> [filter property => value] for filters that are set and allowed. */
    protected function activeFilterValues(): array
    {
        $values = [];

        foreach ($this->filters() as $filter) {
            $value = $this->{$filter->property};

            if ($value !== '' && $filter->allows($value)) {
                $values[$filter->property] = $value;
            }
        }

        return $values;
    }

    protected function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->activeFilterValues() !== [];
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->selectedUnit = '';
        $this->severityFilter = '';
        $this->resetPage();
    }

    /** Empty values (no holder, never filled) always sort last, whichever the direction. */
    private function sortVacancies(Collection $vacancies): Collection
    {
        if ($this->sortField === '') {
            return $vacancies;
        }

        $property = self::SORTABLE[$this->sortField];
        $direction = $this->sortDirection === 'desc' ? -1 : 1;

        $filled = $vacancies->filter(fn (object $row) => ($row->{$property} ?? null) !== null)
            ->sort(function (object $a, object $b) use ($property, $direction): int {
                $left = $a->{$property};
                $right = $b->{$property};
                $result = is_int($left) && is_int($right) ? $left <=> $right : strnatcasecmp((string) $left, (string) $right);

                return $result * $direction;
            });
        $empty = $vacancies->filter(fn (object $row) => ($row->{$property} ?? null) === null);

        return $filled->concat($empty)->values();
    }

    public function render(JobVacancyService $service): View
    {
        // Re-validate on every render: a stale or tampered value must never reach the service.
        foreach ($this->filters() as $filter) {
            $this->normaliseFilter($filter);
        }
        $this->normalisePerPage();
        $this->normaliseSort();

        $baseVacancies = $service->filterVacancies(
            $this->allVacancies($service),
            search: $this->search ?: null,
            unitKerja: $this->selectedUnit ?: null,
        );

        $counts = [
            'all' => $baseVacancies->count(),
            'critical' => $baseVacancies->where('severity', 'red')->count(),
            'moderate' => $baseVacancies->where('severity', 'yellow')->count(),
            'recent' => $baseVacancies->where('severity', 'green')->count(),
            'unknown' => $baseVacancies->where('severity', 'unknown')->count(),
        ];

        $filteredVacancies = $this->severityFilter
            ? $baseVacancies->where('severity', $this->severityFilter)
            : $baseVacancies;
        $filteredVacancies = $this->sortVacancies($filteredVacancies);

        // In-memory pagination
        $currentPage = $this->getPage();
        $items = $filteredVacancies->slice(($currentPage - 1) * $this->perPage, $this->perPage)->values();

        $paginatedVacancies = new LengthAwarePaginator(
            $items,
            $filteredVacancies->count(),
            $this->perPage,
            $currentPage,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.tables.job-vacancy-table', [
            'vacancies' => $paginatedVacancies,
            'counts' => $counts,
            'filters' => $this->filters(),
            'filterValues' => $this->activeFilterValues(),
            'hasActiveFilters' => $this->hasActiveFilters(),
            'perPageOptions' => $this->perPageOptions(),
            'sortField' => $this->sortField,
            'sortDirection' => $this->sortDirection,
        ]);
    }

    /** @return Collection<int, object> */
    private function allVacancies(?JobVacancyService $service = null): Collection
    {
        return $this->allVacancies ??= ($service ?? app(JobVacancyService::class))->getVacancies();
    }

    /** @return array<string, string> [unit => unit] of the units that currently have vacancies. */
    private function unitOptions(): array
    {
        if ($this->unitOptions === null) {
            $units = $this->allVacancies()->pluck('unit_kerja')->unique()->sort()->values()->map(fn ($unit) => (string) $unit)->all();
            $this->unitOptions = array_combine($units, $units) ?: [];
        }

        return $this->unitOptions;
    }

    private function normaliseSearch(string $search): string
    {
        return mb_substr(trim($search), 0, self::SEARCH_MAX_LENGTH);
    }

    private function normalisePerPage(): void
    {
        if (! in_array($this->perPage, $this->perPageOptions(), true)) {
            $this->perPage = (int) config('hris.pagination.default');
        }
    }

    /** A tampered sort column or direction falls back to the default order. */
    private function normaliseSort(): void
    {
        if (! array_key_exists($this->sortField, self::SORTABLE)) {
            $this->sortField = '';
        }

        if (! in_array($this->sortDirection, ['asc', 'desc'], true)) {
            $this->sortDirection = 'asc';
        }
    }

    private function normaliseFilter(Filter $filter): void
    {
        if (! $filter->allows($this->{$filter->property})) {
            $this->{$filter->property} = '';
        }
    }
}
