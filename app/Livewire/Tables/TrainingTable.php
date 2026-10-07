<?php

namespace App\Livewire\Tables;

use App\Models\Training;
use Livewire\Component;
use Livewire\WithPagination;

class TrainingTable extends Component
{
    use WithPagination;

    public string $employeeId;

    public string $search = '';

    public string $sortField = 'tanggal_mulai';

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

    public function detachTraining(int $id): void
    {
        $training = Training::findOrFail($id);
        $training->employees()->detach($this->employeeId);

        session()->flash('message', 'Pelatihan berhasil dihapus dari karyawan.');
    }

    public function render()
    {
        $trainings = Training::query()
            ->whereHas('employees', fn ($q) => $q->where('employee_nik', $this->employeeId))
            ->with(['employees' => fn ($q) => $q->where('employee_nik', $this->employeeId)])
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('judul', 'like', '%'.$this->search.'%')
                        ->orWhere('penyelenggara', 'like', '%'.$this->search.'%')
                        ->orWhere('jenis', 'like', '%'.$this->search.'%')
                        ->orWhere('metode', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.tables.training-table', [
            'trainings' => $trainings,
        ]);
    }
}
