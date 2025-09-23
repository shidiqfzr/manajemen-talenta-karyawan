<?php

namespace App\Livewire\Tables;

use App\Models\JobHistory;
use Illuminate\Database\Eloquent\Builder;
use Rappasoft\LaravelLivewireTables\DataTableComponent;
use Rappasoft\LaravelLivewireTables\Views\Column;

class JobHistoryTable extends DataTableComponent
{
    public string $employeeId;

    public function mount(string $employeeId): void
    {
        $this->employeeId = $employeeId;
    }

    public function configure(): void
    {
        $this->setPerPageAccepted([5, 10, 25]);
        $this->setPerPage(5);

        $this->setPrimaryKey('id');

        $this->setColumnSelectStatus(true);

        $this->setTrAttributes(fn($row) => [
            'class' => 'hover:bg-gray-50',
        ]);
    }

    public function builder(): Builder
    {
        return JobHistory::query()
            ->select('job_histories.*')
            ->where('employee_nik', $this->employeeId)
            ->orderByDesc('tmt_awal');
    }

    public function columns(): array
    {
        return [
            Column::make('Periode', 'tmt_awal')
                ->format(fn($value, $row) => $this->formatPeriode($row))
                ->html(),

            Column::make('Jabatan', 'jabatan')
                ->sortable()
                ->searchable(),

            Column::make('Unit', 'unit_kerja')
                ->sortable()
                ->searchable(),

            Column::make('Mutasi', 'jenis_mutasi')
                ->format(fn($value, $row) => $row->jenis_mutasi_label ?? '–'),

            Column::make('Level', 'level')
                ->sortable()
                ->deselected(),

            Column::make('Golongan', 'golongan')
                ->sortable()
                ->deselected(),

            Column::make('SK', 'nomor_sk')
                ->format(fn($value, $row) => $this->formatSk($row))
                ->html()
                ->deselected(),

            Column::make('Aksi', 'id')
                ->format(fn($value, $row) => view('admin.employees.partials.job-history-actions')->with('row', $row)->render())
                ->html()
                ->excludeFromColumnSelect(),
        ];
    }

    protected function formatPeriode(JobHistory $row): string
    {
        $start = $row->tmt_awal?->format('d M Y') ?? '-';
        $end = $row->tmt_akhir?->format('d M Y') ?? 'Sekarang';
        return "{$start} &ndash; {$end}";
    }

    protected function formatSk(JobHistory $row): string
    {
        if ($row->nomor_sk || $row->tanggal_sk) {
            $nomor = $row->nomor_sk ?? '–';
            $tgl = $row->tanggal_sk?->format('d M Y') ?? '–';
            return "{$nomor} / {$tgl}";
        }

        return '–';
    }

    public function deleteRow(int $id): void
    {
        $record = JobHistory::where('employee_nik', $this->employeeId)->findOrFail($id);
        $record->delete();

        session()->flash('message', 'Riwayat jabatan dihapus.');
    }
}
