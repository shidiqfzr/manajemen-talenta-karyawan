<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Evaluation;
use App\Support\DataTable\Column;
use App\Support\DataTable\QueryPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Evaluation (assessment and 9-box) history of one employee, embedded on the employee profile page.
 */
class EvaluationTable extends Component
{
    use WithDataTable;

    /** NIK of the profiled employee; locked so the client cannot read another employee's scores. */
    #[Locked]
    public string $employeeId;

    public function mount(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function deleteRow(int $id): void
    {
        $record = Evaluation::where('employee_nik', $this->employeeId)->findOrFail($id);
        $record->delete();

        session()->flash('message', 'Data evaluasi berhasil dihapus.');
    }

    protected function baseQuery(): Builder
    {
        return Evaluation::query()->where('employee_nik', $this->employeeId);
    }

    protected function columns(): array
    {
        return [
            Column::make('tanggal_pelaksanaan_asesmen', 'Periode Asesmen')->sortable(),
            Column::make('nilai_tertimbang', 'Nilai Tertimbang')->sortable()->mono(),
            Column::make('kategori_9box', '9-Box Performance')->searchable(),
            Column::make('bidang_tugas', 'Bidang Tugas')->sortable()->searchable(),
            // One visible column shows both the assessing institution and the assessment category.
            Column::make('lembaga_asesmen', 'Lembaga & Tanggal')->searchable(fn (Builder $q, string $term) => $this->whereLikeAny($q, ['lembaga_asesmen', 'kategori_asesmen'], $term)),
            Column::make('aksi', 'Aksi')->align('center'),
        ];
    }

    protected function defaultSort(): array
    {
        return ['tanggal_pelaksanaan_asesmen', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Riwayat Evaluasi';
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
        return 'evaluasi_page';
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
        return view('livewire.tables.evaluation-table');
    }
}
