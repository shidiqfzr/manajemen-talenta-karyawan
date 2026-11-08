<?php

namespace App\Livewire\Admin;

use App\Livewire\Concerns\WithDataTable;
use App\Models\Employee;
use App\Support\DataTable\Column;
use App\Support\DataTable\Filter;
use App\Support\DataTable\QueryPipeline;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Participants of one training, embedded on the admin training detail page.
 * Edit and remove stay a plain link and a CSRF-protected DELETE form handled by TrainingParticipantController.
 */
class TrainingParticipantTable extends Component
{
    use WithDataTable;

    /** Locked so the client cannot repoint the table to another training's participants. */
    #[Locked]
    public int $trainingId;

    /** Level filter value; not synced to the URL because the table is embedded in another page. */
    public string $level = '';

    /**
     * Admin-only screen: route middleware is not re-applied to Livewire update requests, so refuse
     * non-admins on every request.
     */
    public function boot(): void
    {
        abort_unless(auth()->user()?->role === 'admin', 403, 'Unauthorized.');
    }

    public function mount(int $trainingId): void
    {
        $this->trainingId = $trainingId;
    }

    /**
     * Employees attached to the training. The pivot (certificate) is read through a constrained
     * `trainings` eager load, so the main query only touches the employees table and no column collides.
     */
    protected function baseQuery(): Builder
    {
        return Employee::query()
            ->whereHas('trainings', fn ($q) => $q->whereKey($this->trainingId))
            ->with(['trainings' => fn ($q) => $q->whereKey($this->trainingId)]);
    }

    protected function rowKey(): string
    {
        return 'nik';
    }

    protected function columns(): array
    {
        return [
            // One visible column shows both the NIK and the name.
            Column::make('pegawai', 'Pegawai')->searchable(fn (Builder $q, string $term) => QueryPipeline::whereLikeAny($q, ['nik', 'nama'], $term)),
            Column::make('jabatan', 'Jabatan')->searchable(),
            Column::make('unit_kerja', 'Unit')->searchable(),
            Column::make('level', 'Level')->sortable(),
            Column::make('sertifikat', 'Sertifikat'),
            Column::make('aksi', 'Aksi')->align('center'),
        ];
    }

    protected function filters(): array
    {
        return [
            Filter::select('level', 'Level', ['Karpim' => 'Karpim', 'Karpel' => 'Karpel'])
                ->icon('fas fa-layer-group')
                ->allLabel('Semua Level')
                ->apply(fn (Builder $query, string $value) => $query->where($query->qualifyColumn('level'), $value)),
        ];
    }

    protected function defaultSort(): array
    {
        return ['nama', 'asc'];
    }

    protected function tableLabel(): string
    {
        return 'Daftar Peserta';
    }

    /** @return array<int, int> */
    protected function perPageOptions(): array
    {
        return [10, 20, 50, 100];
    }

    protected function defaultPerPage(): int
    {
        return 20;
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
        return 'peserta';
    }

    public function render(): View
    {
        return view('livewire.admin.training-participant-table');
    }
}
