<?php

namespace App\Livewire\Tables;

use App\Services\JobVacancyService;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class JobVacancyTable extends Component
{
    use WithPagination;

    public string $search = '';

    public string $selectedUnit = '';

    public string $severityFilter = '';

    public int $perPage = 10;

    protected $queryString = [
        'search' => ['except' => ''],
        'selectedUnit' => ['except' => ''],
        'severityFilter' => ['except' => ''],
    ];

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedUnit(): void
    {
        $this->resetPage();
    }

    public function updatingSeverityFilter(): void
    {
        $this->resetPage();
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

        $units = $service->getVacantUnits();

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
            'units' => $units,
            'counts' => $counts,
        ]);
    }
}
