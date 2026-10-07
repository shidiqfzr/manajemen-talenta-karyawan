<?php

namespace App\Livewire\Admin;

use App\Models\JobHistory;
use App\Services\MutasiService;
use Livewire\Component;
use Livewire\WithPagination;

class MutasiHistory extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $mutasiFilter = '';

    public int $perPage = 15;

    protected $queryString = [
        'search' => ['except' => ''],
        'unitFilter' => ['except' => ''],
        'mutasiFilter' => ['except' => ''],
    ];

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

    public function render(MutasiService $service)
    {
        $history = $service->getMutasiHistory(
            search: $this->search ?: null,
            unitKerja: $this->unitFilter ?: null,
            jenisMutasi: $this->mutasiFilter ?: null,
            perPage: $this->perPage,
        );
        $units = $service->getKnownUnits();
        $mutasiTypes = JobHistory::MUTASI_TYPES;

        return view('livewire.admin.mutasi-history', compact('history', 'units', 'mutasiTypes'));
    }
}
