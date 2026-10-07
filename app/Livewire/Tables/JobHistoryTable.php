<?php

namespace App\Livewire\Tables;

use App\Models\JobHistory;
use Livewire\Component;
use Livewire\WithPagination;

class JobHistoryTable extends Component
{
    use WithPagination;

    public string $employeeId;

    public string $search = '';

    public string $sortField = 'tmt_awal';

    public string $sortDirection = 'desc';

    public int $perPage = 5;

    public function mount(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function deleteRow(int $id): void
    {
        $record = JobHistory::where('employee_nik', $this->employeeId)->findOrFail($id);
        $record->delete();

        session()->flash('message', 'Riwayat jabatan berhasil dihapus.');
    }

    public function render()
    {
        $histories = JobHistory::query()
            ->where('employee_nik', $this->employeeId)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('jabatan', 'like', '%'.$this->search.'%')
                        ->orWhere('unit_kerja', 'like', '%'.$this->search.'%')
                        ->orWhere('jenis_mutasi', 'like', '%'.$this->search.'%')
                        ->orWhere('nomor_sk', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.tables.job-history-table', [
            'histories' => $histories,
        ]);
    }
}
