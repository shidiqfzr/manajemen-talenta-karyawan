<?php

namespace App\Livewire\Tables;

use App\Livewire\Concerns\WithDataTable;
use App\Models\JobHistory;
use App\Support\DataTable\Column;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Job (position) history of one employee, embedded on the employee profile page.
 */
class JobHistoryTable extends Component
{
    use WithDataTable;

    /** NIK of the profiled employee; locked so the client cannot repoint the table to another employee. */
    #[Locked]
    public string $employeeId;

    public function mount(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function deleteRow(int $id): void
    {
        $record = JobHistory::where('employee_nik', $this->employeeId)->findOrFail($id);
        $record->delete();

        session()->flash('message', 'Riwayat jabatan berhasil dihapus.');
    }

    protected function baseQuery(): Builder
    {
        return JobHistory::query()->where('employee_nik', $this->employeeId);
    }

    protected function columns(): array
    {
        return [
            Column::make('tmt_awal', 'Periode')->sortable(),
            Column::make('jabatan', 'Jabatan')->sortable()->searchable(),
            Column::make('unit_kerja', 'Unit Kerja')->sortable()->searchable(),
            Column::make('level', 'Level / Golongan'),
            Column::make('jenis_mutasi', 'Mutasi')->searchable(),
            Column::make('nomor_sk', 'Nomor & Tanggal SK')->searchable(),
            Column::make('aksi', 'Aksi')->align('center'),
        ];
    }

    protected function defaultSort(): array
    {
        return ['tmt_awal', 'desc'];
    }

    protected function tableLabel(): string
    {
        return 'Riwayat Jabatan';
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
        return 'riwayat_jabatan_page';
    }

    public function render(): View
    {
        return view('livewire.tables.job-history-table');
    }
}
