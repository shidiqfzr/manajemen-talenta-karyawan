<?php

namespace App\Livewire\Tables;

use App\Services\JobVacancyService;
use App\Support\DataTable\Filter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
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

    public int $perPage = 10;

    /** @var ?array<string, string> */
    private ?array $unitOptions = null;

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
                'yellow' => 'Sedang (30–90 hari)',
                'green' => 'Baru (< 30 hari)',
            ])->withoutControl(),
        ];
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 25, 50];
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

    public function render(JobVacancyService $service): View
    {
        // Re-validate on every render: a stale or tampered value must never reach the service.
        foreach ($this->filters() as $filter) {
            $this->normaliseFilter($filter);
        }
        $this->normalisePerPage();

        $baseVacancies = $service->getVacancies(
            search: $this->search ?: null,
            unitKerja: $this->selectedUnit ?: null,
        );

        $counts = [
            'all' => $baseVacancies->count(),
            'critical' => $baseVacancies->where('severity', 'red')->count(),
            'moderate' => $baseVacancies->where('severity', 'yellow')->count(),
            'recent' => $baseVacancies->where('severity', 'green')->count(),
        ];

        $filteredVacancies = $this->severityFilter
            ? $baseVacancies->where('severity', $this->severityFilter)
            : $baseVacancies;

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
        ]);
    }

    /** @return array<string, string> [unit => unit] of the units that currently have vacancies. */
    private function unitOptions(): array
    {
        if ($this->unitOptions === null) {
            $units = app(JobVacancyService::class)->getVacantUnits()->map(fn ($unit) => (string) $unit)->all();
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
            $this->perPage = 10;
        }
    }

    private function normaliseFilter(Filter $filter): void
    {
        if (! $filter->allows($this->{$filter->property})) {
            $this->{$filter->property} = '';
        }
    }
}
