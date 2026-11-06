<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Training;
use App\Support\DataTable\Column;
use App\Support\DataTable\QueryPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Training history of one employee, embedded on the employee profile page.
 */
class TrainingTable extends Component
{
    use WithDataTable;

    /** NIK of the profiled employee; locked so the client cannot repoint the table to another employee. */
    #[Locked]
    public string $employeeId;

    public function mount(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function detachTraining(int $id): void
    {
        $training = Training::findOrFail($id);
        $training->employees()->detach($this->employeeId);

        session()->flash('message', 'Pelatihan berhasil dihapus dari karyawan.');
    }

    protected function baseQuery(): Builder
    {
        return Training::query()
            ->whereHas('employees', fn ($q) => $q->where('employee_nik', $this->employeeId))
            ->with(['employees' => fn ($q) => $q->where('employee_nik', $this->employeeId)]);
    }

    protected function columns(): array
    {
        return [
            Column::make('judul', 'Judul Pelatihan')->sortable()->searchable(),
            Column::make('tanggal_mulai', 'Tanggal')->sortable(),
            Column::make('penyelenggara', 'Penyelenggara')->sortable()->searchable(),
            // One visible column shows both the training type and its method.
            Column::make('jenis', 'Jenis / Metode')->searchable(fn (Builder $q, string $term) => $this->whereLikeAny($q, ['jenis', 'metode'], $term)),
            Column::make('sertifikat', 'Sertifikat')->align('center'),
            Column::make('aksi', 'Aksi')->align('center'),
        ];
    }

    protected function defaultSort(): array
    {
        return ['tanggal_mulai', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Riwayat Pelatihan';
    }

    protected function perPageOptions(): array
    {
        return [5, 10, 25, 50];
    }

    protected function defaultPerPage(): int
    {
        return 5;
    }

    protected function exportName(): ?string
    {
        return null;
    }

    protected function syncsUrl(): bool
    {
        return false;
    }

    protected function paginationName(): string
    {
        return 'pelatihan_page';
    }

    /** @param array<int, string> $columns */
    private function whereLikeAny(Builder $query, array $columns, string $term): void
    {
        $grammar = $query->getQuery()->getGrammar();
        $pattern = '%'.QueryPipeline::escapeLike($term).'%';

        foreach ($columns as $column) {
            $query->orWhereRaw($grammar->wrap($query->qualifyColumn($column))." LIKE ? ESCAPE '!'", [$pattern]);
        }
    }

    public function render(): View
    {
        return view('livewire.tables.training-table');
    }
}
