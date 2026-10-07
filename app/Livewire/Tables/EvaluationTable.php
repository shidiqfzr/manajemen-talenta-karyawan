<?php

namespace App\Livewire\Tables;

use App\Models\Evaluation;
use Livewire\Component;
use Livewire\WithPagination;

class EvaluationTable extends Component
{
    use WithPagination;

    public string $employeeId;

    public string $search = '';

    public string $sortField = 'tanggal_pelaksanaan_asesmen';

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
        $record = Evaluation::where('employee_nik', $this->employeeId)->findOrFail($id);
        $record->delete();

        session()->flash('message', 'Data evaluasi berhasil dihapus.');
    }

    public function render()
    {
        $evaluations = Evaluation::query()
            ->where('employee_nik', $this->employeeId)
            ->when($this->search, function ($query) {
                $query->where(function ($q) {
                    $q->where('bidang_tugas', 'like', '%'.$this->search.'%')
                        ->orWhere('lembaga_asesmen', 'like', '%'.$this->search.'%')
                        ->orWhere('kategori_9box', 'like', '%'.$this->search.'%')
                        ->orWhere('kategori_asesmen', 'like', '%'.$this->search.'%');
                });
            })
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate($this->perPage);

        return view('livewire.tables.evaluation-table', [
            'evaluations' => $evaluations,
        ]);
    }
}
